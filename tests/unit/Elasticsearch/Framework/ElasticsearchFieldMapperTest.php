<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchFieldMapper::class)]
class ElasticsearchFieldMapperTest extends TestCase
{
    public function testMapTranslatedField(): void
    {
        $items = [['name' => 'foo', 'languageId' => 'de-DE'], ['name' => null, 'languageId' => 'en-GB']];
        $fallbackItems = [['name' => 'foo-baz', 'languageId' => 'de-DE'], ['name' => 'bar', 'languageId' => 'en-GB'], ['name' => 'baz'], ['name' => 'foo VI', 'languageId' => 'vi-VN']];
        $fieldValue = ElasticsearchFieldMapper::translated('name', $items, $fallbackItems);

        static::assertEquals(['de-DE' => 'foo', 'en-GB' => 'bar', 'vi-VN' => 'foo VI', Defaults::LANGUAGE_SYSTEM => 'baz'], $fieldValue);
    }

    public function testMapToManyAssociations(): void
    {
        $items = [
            ['id' => 'fooId', 'name' => 'foo in EN', 'languageId' => 'en-GB'],
            ['id' => 'fooId', 'name' => 'foo in DE', 'languageId' => 'de-DE'],
            ['id' => 'barId', 'name' => 'bar', 'description' => 'bar description', 'languageId' => 'en-GB'],
        ];

        $fieldValue = ElasticsearchFieldMapper::toManyAssociations($items, ['name', 'description']);

        static::assertEquals([
            [
                'id' => 'fooId',
                '_count' => 1,
                'name' => [
                    'en-GB' => 'foo in EN',
                    'de-DE' => 'foo in DE',
                ],
                'description' => [
                    'en-GB' => null,
                ],
            ], [
                'id' => 'barId',
                '_count' => 1,
                'name' => ['en-GB' => 'bar'],
                'description' => [
                    'en-GB' => 'bar description',
                ],
            ],
        ], $fieldValue);
    }

    public function testMapCustomFields(): void
    {
        $deLanguageId = Uuid::randomHex();
        $enLanguageId = Uuid::randomHex();

        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag(['elasticsearch.product.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchAllKeyValue')->willReturn([
            'cf_bool' => 'bool',
            'cf_text' => 'text',
        ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $mapper = new ElasticsearchFieldMapper($utils);

        $formatted = $mapper->customFields(ProductDefinition::ENTITY_NAME, [
            $deLanguageId => [
                'cf_foo' => 'danke',
                'cf_baz' => '234',
                'cf_bool' => 0,
                'cf_text' => 'text',
            ],
            $enLanguageId => [
                'cf_foo' => 'thankyou',
                'cf_baz' => '123',
                'cf_bool' => 'true',
                'cf_text' => '10.0',
            ],
        ], new Context(new SystemSource()));

        static::assertSame([
            $deLanguageId => [
                'cf_foo' => 'danke',
                'cf_baz' => 234.0,
                'cf_bool' => false,
                'cf_text' => 'text',

            ],
            $enLanguageId => [
                'cf_foo' => 'thankyou',
                'cf_baz' => 123.0,
                'cf_bool' => true,
                'cf_text' => '10.0',
            ],
        ], $formatted);
    }
}
