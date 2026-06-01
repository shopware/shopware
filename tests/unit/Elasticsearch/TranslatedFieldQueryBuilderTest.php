<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use OpenSearchDSL\Query\Compound\DisMaxQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\AbstractFieldQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\ResolvedField;
use Shopware\Elasticsearch\TranslatedFieldQueryBuilder;
use Shopware\Elasticsearch\TranslatedResolvedField;

/**
 * @internal
 */
#[CoversClass(TranslatedFieldQueryBuilder::class)]
#[Package('inventory')]
class TranslatedFieldQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    public function testGetDecorated(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));

        static::assertSame($inner, $builder->getDecorated());
    }

    public function testDelegatesNonTranslatedField(): void
    {
        $expected = new TermQuery('stock', 42);

        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->expects($this->once())
            ->method('build')
            ->willReturn($expected);

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $field = new ResolvedField(new IntField('stock', 'stock'));
        $config = new SearchFieldConfig('stock', 300, false);

        $query = $builder->build($field, '42', $config, Context::createDefaultContext());

        static::assertSame($expected, $query);
    }

    public function testBuildsLanguageChainForTranslatedField(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')
            ->willReturnCallback(function (ResolvedField $field, string $token, SearchFieldConfig $config): TermQuery {
                return new TermQuery($config->getField(), $token, ['boost' => $config->getRanking()]);
            });

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $translatedField = new TranslatedField('name');
        $field = new TranslatedResolvedField(new StringField('name', 'name'), $translatedField);
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID]]);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNotNull($query);
        static::assertInstanceOf(DisMaxQuery::class, $query);

        $array = $query->toArray();
        $queries = $array['dis_max']['queries'];
        static::assertCount(2, $queries);

        // First language gets original ranking
        static::assertSame('name.' . Defaults::LANGUAGE_SYSTEM, array_key_first($queries[0]['term']));
        static::assertSame(500.0, $queries[0]['term']['name.' . Defaults::LANGUAGE_SYSTEM]['boost']);

        // Second language gets 80% ranking
        static::assertSame('name.' . self::SECOND_LANGUAGE_ID, array_key_first($queries[1]['term']));
        static::assertSame(400.0, $queries[1]['term']['name.' . self::SECOND_LANGUAGE_ID]['boost']);
    }

    public function testSingleLanguageReturnsUnwrappedQuery(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')
            ->willReturnCallback(function (ResolvedField $field, string $token, SearchFieldConfig $config): TermQuery {
                return new TermQuery($config->getField(), $token, ['boost' => $config->getRanking()]);
            });

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $translatedField = new TranslatedField('name');
        $field = new TranslatedResolvedField(new StringField('name', 'name'), $translatedField);
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNotNull($query);
        static::assertInstanceOf(TermQuery::class, $query);
    }

    public function testReturnsNullWhenAllLanguageQueriesReturnNull(): void
    {
        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')->willReturn(null);

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $translatedField = new TranslatedField('name');
        $field = new TranslatedResolvedField(new StringField('name', 'name'), $translatedField);
        $config = new SearchFieldConfig('name', 500, false);

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID]]);

        $query = $builder->build($field, 'foo', $config, $context);

        static::assertNull($query);
    }

    public function testCustomFieldTranslatedFieldNameFormat(): void
    {
        $capturedConfigs = [];

        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')
            ->willReturnCallback(function (ResolvedField $field, string $token, SearchFieldConfig $config) use (&$capturedConfigs): TermQuery {
                $capturedConfigs[] = $config;

                return new TermQuery($config->getField(), $token, ['boost' => $config->getRanking()]);
            });

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $translatedField = new TranslatedField('customFields');
        $field = new TranslatedResolvedField(new StringField('evolvesText', 'evolvesText'), $translatedField);
        $config = new SearchFieldConfig('customFields.evolvesText', 500, false);

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $builder->build($field, 'foo', $config, $context);

        static::assertCount(1, $capturedConfigs);
        static::assertSame('customFields.' . Defaults::LANGUAGE_SYSTEM . '.evolvesText', $capturedConfigs[0]->getField());
    }

    public function testStripsRootFromDelegatedResolvedField(): void
    {
        $capturedFields = [];

        $inner = $this->createMock(AbstractFieldQueryBuilder::class);
        $inner->method('build')
            ->willReturnCallback(function (ResolvedField $field, string $token, SearchFieldConfig $config) use (&$capturedFields): TermQuery {
                $capturedFields[] = $field;

                return new TermQuery($config->getField(), $token);
            });

        $builder = new TranslatedFieldQueryBuilder($inner, new ArrayKeyValueStorage([]));
        $translatedField = new TranslatedField('name');
        $field = new TranslatedResolvedField(new StringField('name', 'name'), $translatedField, 'categories');
        $config = new SearchFieldConfig('categories.name', 500, false);

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $builder->build($field, 'foo', $config, $context);

        static::assertCount(1, $capturedFields);
        static::assertNotInstanceOf(TranslatedResolvedField::class, $capturedFields[0]);
        static::assertNull($capturedFields[0]->getRoot());
    }
}
