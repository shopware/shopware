<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\AbstractFieldQueryBuilder;
use Shopware\Elasticsearch\ExplainFieldQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\ResolvedField;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ExplainFieldQueryBuilder::class)]
class ExplainFieldQueryBuilderTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $builder = new ExplainFieldQueryBuilder($inner);

        static::assertSame($inner, $builder->getDecorated());
    }

    public function testDelegatesWithoutExplainMode(): void
    {
        $expected = new TermQuery('name', 'foo');
        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn($expected);

        $builder = new ExplainFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $query = $builder->build($field, 'foo', $config, Context::createDefaultContext());

        static::assertSame($expected, $query);
        $array = $query->toArray();
        // Without explain mode, the _name parameter should not be added
        static::assertSame('foo', $array['term']['name']);
    }

    public function testAddsExplainMetadata(): void
    {
        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn(new TermQuery('name', 'foo'));

        $builder = new ExplainFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNotNull($query);
        $array = $query->toArray();
        static::assertArrayHasKey('_name', $array['term']['name']);

        $payload = json_decode($array['term']['name']['_name'], true);
        static::assertSame('name', $payload['field']);
        static::assertSame('foo', $payload['term']);
        static::assertSame(500, $payload['ranking']);
    }

    public function testAddsInnerHitsForNestedQuery(): void
    {
        $innerQuery = new TermQuery('tags.name', 'foo');
        $nestedQuery = new NestedQuery('tags', $innerQuery);

        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn($nestedQuery);

        $builder = new ExplainFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'), 'tags');
        $config = new SearchFieldConfig('tags.name', 500, false);

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNotNull($query);
        static::assertInstanceOf(NestedQuery::class, $query);
        $array = $query->toArray();
        static::assertArrayHasKey('inner_hits', $array['nested']);
        static::assertArrayHasKey('_name', $array['nested']);
        static::assertFalse($array['nested']['inner_hits']['_source']);
        static::assertTrue($array['nested']['inner_hits']['explain']);
    }

    public function testDisMaxQueryIsReturnedUnchangedInExplainMode(): void
    {
        // A text field produces a DisMax whose individual clauses are already named by
        // FieldQueryBuilder, so the decorator must return it untouched rather than add a
        // second, field-level _name on top.
        $disMax = new DisMaxQuery();
        $disMax->addQuery(new TermQuery('name.search', 'foo'));

        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn($disMax);

        $builder = new ExplainFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertSame($disMax, $query);
        static::assertArrayNotHasKey('_name', $query->toArray()['dis_max']);
    }

    public function testReturnsNullWhenInnerReturnsNull(): void
    {
        $inner = static::createStub(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn(null);

        $builder = new ExplainFieldQueryBuilder($inner);
        $field = new ResolvedField(new StringField('name', 'name'));
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNull($query);
    }
}
