<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\FieldQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\ResolvedField;

/**
 * @internal
 */
#[CoversClass(FieldQueryBuilder::class)]
#[Package('inventory')]
class FieldQueryBuilderTest extends TestCase
{
    public function testGetDecoratedThrowsException(): void
    {
        $builder = new FieldQueryBuilder();

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    public function testStringFieldReturnDisMaxQuery(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(DisMaxQuery::class, $query);
    }

    public function testLongTextFieldReturnDisMaxQuery(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new LongTextField('description', 'description'));
        $config = new SearchFieldConfig('description', 500, false, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(DisMaxQuery::class, $query);
    }

    public function testListFieldReturnDisMaxQuery(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new ListField('tags', 'tags'));
        $config = new SearchFieldConfig('tags', 500, false, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(DisMaxQuery::class, $query);
    }

    public function testMultiTokenTextFieldUsesAndLogic(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true);

        $query = $builder->build($field, 'foo bar', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $exactMatch = $array['dis_max']['queries'][0] ?? null;
        static::assertNotNull($exactMatch);
        static::assertArrayHasKey('bool', $exactMatch);
        static::assertArrayHasKey('must', $exactMatch['bool']);
    }

    public function testMultiTokenTextFieldUsesOrLogic(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, false, true);

        $query = $builder->build($field, 'foo bar', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $exactMatch = $array['dis_max']['queries'][0] ?? null;
        static::assertNotNull($exactMatch);
        static::assertArrayHasKey('terms', $exactMatch);
    }

    public function testSingleTokenExactMatchUsesExactSubfieldWhenConfigured(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $exactMatch = $array['dis_max']['queries'][0] ?? null;
        static::assertNotNull($exactMatch);
        static::assertStringContainsString(
            '"name.exact"',
            json_encode($exactMatch, \JSON_THROW_ON_ERROR),
        );
    }

    public function testSingleTokenWithoutExactSubfieldUsesSearchMatchQuery(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $exactMatch = $array['dis_max']['queries'][0] ?? null;
        static::assertNotNull($exactMatch);
        static::assertArrayHasKey('match', $exactMatch);
        static::assertArrayHasKey('name.search', $exactMatch['match']);
        static::assertEquals(1.0, $exactMatch['match']['name.search']['boost']);
        static::assertSame(0, $exactMatch['match']['name.search']['fuzziness']);
        static::assertSame('and', $exactMatch['match']['name.search']['operator']);
    }

    public function testMultiTokenExactMatchDoesNotUseExactSubfieldWhenConfigured(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true, true);

        $query = $builder->build($field, 'foo bar', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $exactMatch = $array['dis_max']['queries'][0] ?? null;
        static::assertNotNull($exactMatch);
        static::assertStringNotContainsString(
            '"name.exact"',
            json_encode($exactMatch, \JSON_THROW_ON_ERROR),
        );
        static::assertStringContainsString(
            '"name"',
            json_encode($exactMatch, \JSON_THROW_ON_ERROR),
        );
    }

    public function testNgramQueryIncludedForLongTokenizedTerm(): void
    {
        $builder = new FieldQueryBuilder(4);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, true, true, true);

        $query = $builder->build($field, 'foobar', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $queries = $array['dis_max']['queries'];
        $hasNgram = false;
        foreach ($queries as $q) {
            if (isset($q['match']) && array_key_first($q['match']) === 'name.ngram') {
                $hasNgram = true;
            }
        }
        static::assertTrue($hasNgram);
    }

    public function testNgramQueryNotIncludedForShortTerm(): void
    {
        $builder = new FieldQueryBuilder(4);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, true, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $queries = $array['dis_max']['queries'];
        foreach ($queries as $q) {
            if (isset($q['match'])) {
                static::assertNotSame('name.ngram', array_key_first($q['match']));
            }
        }
    }

    public function testPrefixMatchDisabled(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        $queries = $array['dis_max']['queries'];
        foreach ($queries as $q) {
            static::assertArrayNotHasKey('match_bool_prefix', $q);
        }
    }

    public function testLanguageAnalyzerDisabledUsesWhitespaceAnalyzer(): void
    {
        $builder = new FieldQueryBuilder(4, false);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false, true, true);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        $array = $query->toArray();
        static::assertArrayHasKey('match', $array['dis_max']['queries'][0]);
        $matchValues = reset($array['dis_max']['queries'][0]['match']);
        static::assertSame('sw_whitespace_analyzer', $matchValues['analyzer'] ?? null);
    }

    #[DataProvider('boolFieldProvider')]
    public function testBoolField(string $token, ?bool $expectedValue): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new BoolField('active', 'active'));
        $config = new SearchFieldConfig('active', 500, false);

        $query = $builder->build($field, $token, $config, Context::createDefaultContext());

        if ($expectedValue === null) {
            static::assertNull($query);

            return;
        }

        static::assertNotNull($query);
        static::assertInstanceOf(TermQuery::class, $query);
        $array = $query->toArray();
        static::assertSame($expectedValue, $array['term']['active']['value']);
        static::assertSame(500.0, $array['term']['active']['boost']);
    }

    public static function boolFieldProvider(): \Generator
    {
        yield 'true string' => ['true', true];
        yield '1 string' => ['1', true];
        yield 'false string' => ['false', false];
        yield '0 string' => ['0', false];
        yield 'non-boolean string' => ['hello', null];
        yield 'numeric string' => ['42', null];
    }

    public function testIntFieldWithNumericToken(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new IntField('stock', 'stock'));
        $config = new SearchFieldConfig('stock', 300, false);

        $query = $builder->build($field, '42', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(TermQuery::class, $query);
        $array = $query->toArray();
        static::assertSame(42, $array['term']['stock']['value']);
    }

    public function testIntFieldWithNonNumericTokenReturnsNull(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new IntField('stock', 'stock'));
        $config = new SearchFieldConfig('stock', 300, false);

        $query = $builder->build($field, 'abc', $config, Context::createDefaultContext());

        static::assertNull($query);
    }

    public function testFloatFieldWithNumericToken(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new FloatField('weight', 'weight'));
        $config = new SearchFieldConfig('weight', 300, false);

        $query = $builder->build($field, '3.14', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(TermQuery::class, $query);
        $array = $query->toArray();
        static::assertSame(3.14, $array['term']['weight']['value']);
    }

    public function testFloatFieldWithNonNumericTokenReturnsNull(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new FloatField('weight', 'weight'));
        $config = new SearchFieldConfig('weight', 300, false);

        $query = $builder->build($field, 'abc', $config, Context::createDefaultContext());

        static::assertNull($query);
    }

    public function testPriceFieldWithNumericToken(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new PriceField('price', 'price'));
        $config = new SearchFieldConfig('price', 300, false);

        $query = $builder->build($field, '9.99', $config, Context::createDefaultContext());

        static::assertNotNull($query);
        static::assertInstanceOf(TermQuery::class, $query);
        $array = $query->toArray();
        static::assertSame(9.99, $array['term']['price']['value']);
    }

    public function testPriceFieldWithNonNumericTokenReturnsNull(): void
    {
        $builder = new FieldQueryBuilder();
        $field = new ResolvedField(new PriceField('price', 'price'));
        $config = new SearchFieldConfig('price', 300, false);

        $query = $builder->build($field, 'abc', $config, Context::createDefaultContext());

        static::assertNull($query);
    }
}
