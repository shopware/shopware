<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use OpenSearch\Client;
use OpenSearch\Exception\BadRequestHttpException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Shopware\Elasticsearch\Product\ElasticsearchProductException;

/**
 * @internal
 */
#[CoversClass(ElasticsearchCustomFieldsMappingHelper::class)]
class ElasticsearchCustomFieldsMappingHelperTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('customFieldTypeProvider')]
    public function testGetTypeFromCustomFieldType(string $type, array $expected): void
    {
        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType($type);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{type: string, expected: array<string, mixed>}>
     */
    public static function customFieldTypeProvider(): iterable
    {
        yield 'int type' => [
            'type' => CustomFieldTypes::INT,
            'expected' => ['type' => 'long'],
        ];

        yield 'float type' => [
            'type' => CustomFieldTypes::FLOAT,
            'expected' => ['type' => 'double'],
        ];

        yield 'bool type' => [
            'type' => CustomFieldTypes::BOOL,
            'expected' => ['type' => 'boolean'],
        ];

        yield 'datetime type' => [
            'type' => CustomFieldTypes::DATETIME,
            'expected' => [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.SSS||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
        ];

        yield 'price type' => [
            'type' => CustomFieldTypes::PRICE,
            'expected' => [
                'type' => 'object',
                'dynamic' => true,
            ],
        ];

        yield 'json type' => [
            'type' => CustomFieldTypes::JSON,
            'expected' => [
                'type' => 'object',
                'dynamic' => true,
            ],
        ];

        yield 'text type (default)' => [
            'type' => CustomFieldTypes::TEXT,
            'expected' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
        ];

        yield 'select type (default)' => [
            'type' => CustomFieldTypes::SELECT,
            'expected' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
        ];

        yield 'html type (default)' => [
            'type' => CustomFieldTypes::HTML,
            'expected' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
        ];

        yield 'unknown type (default)' => [
            'type' => 'unknown',
            'expected' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
        ];
    }

    public function testMapCustomFieldsToEsTypes(): void
    {
        $customFields = [
            'field_int' => CustomFieldTypes::INT,
            'field_bool' => CustomFieldTypes::BOOL,
            'field_text' => CustomFieldTypes::TEXT,
            'field_float' => CustomFieldTypes::FLOAT,
        ];

        $result = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes($customFields);

        static::assertArrayHasKey('field_int', $result);
        static::assertSame(['type' => 'long'], $result['field_int']);

        static::assertArrayHasKey('field_bool', $result);
        static::assertSame(['type' => 'boolean'], $result['field_bool']);

        static::assertArrayHasKey('field_text', $result);
        static::assertSame(AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD, $result['field_text']);

        static::assertArrayHasKey('field_float', $result);
        static::assertSame(['type' => 'double'], $result['field_float']);
    }

    public function testMapCustomFieldsToEsTypesWithEmptyArray(): void
    {
        $result = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes([]);

        static::assertSame([], $result);
    }

    public function testCreateFieldsInIndicesWithEmptyFields(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->never())->method('getAllUsedIndices');

        $client = $this->createMock(Client::class);
        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices([]);
    }

    public function testCreateFieldsInIndicesWithNoIndices(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn([]);

        $client = $this->createMock(Client::class);
        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->never())->method('fetchLanguageIds');

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices(['field1' => ['type' => 'long']]);
    }

    public function testCreateFieldsInIndicesWithNoLanguages(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn(['index1']);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->once())->method('fetchLanguageIds')->willReturn([]);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('putMapping');

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices(['field1' => ['type' => 'long']]);
    }

    public function testCreateFieldsInIndicesSuccess(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn(['sw_product_index']);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->once())->method('fetchLanguageIds')->willReturn(['lang1', 'lang2']);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('get')
            ->with(['index' => 'sw_product_index'])
            ->willReturn([
                'sw_product_index' => [
                    'mappings' => [],
                ],
            ]);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with(static::callback(static function (array $params) {
                return $params['index'] === 'sw_product_index'
                    && isset($params['body']['properties']['customFields']['properties']['lang1'])
                    && isset($params['body']['properties']['customFields']['properties']['lang2'])
                    && $params['body']['properties']['customFields']['properties']['lang1']['properties']['test_field']['type'] === 'long';
            }));

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices(['test_field' => ['type' => 'long']]);
    }

    public function testCreateFieldsInIndicesPreservesSourceIncludes(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn(['sw_product_index']);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->once())->method('fetchLanguageIds')->willReturn(['lang1']);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('get')
            ->with(['index' => 'sw_product_index'])
            ->willReturn([
                'sw_product_index' => [
                    'mappings' => [
                        '_source' => [
                            'includes' => ['id', 'name'],
                        ],
                    ],
                ],
            ]);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with(static::callback(static function (array $params) {
                return isset($params['body']['_source']['includes'])
                    && $params['body']['_source']['includes'] === ['id', 'name'];
            }));

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices(['test_field' => ['type' => 'long']]);
    }

    public function testCreateFieldsInIndicesThrowsOnTypeChange(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn(['sw_product_index']);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->once())->method('fetchLanguageIds')->willReturn(['lang1']);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())->method('get')->willReturn(['sw_product_index' => ['mappings' => []]]);
        $indices->expects($this->once())
            ->method('putMapping')
            ->willThrowException(new BadRequestHttpException('mapper [customFields.lang1.field] cannot be changed from type [long] to [text]'));

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $this->expectException(ElasticsearchProductException::class);
        $this->expectExceptionMessage('custom fields already exist in the index with different types');

        $helper->createFieldsInIndices(['field' => ['type' => 'text']]);
    }

    public function testCreateFieldsInIndicesWithMultipleIndices(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);
        $indexDetector->expects($this->once())->method('getAllUsedIndices')->willReturn(['index1', 'index2']);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $gateway->expects($this->once())->method('fetchLanguageIds')->willReturn(['lang1']);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->exactly(2))
            ->method('get')
            ->willReturn(['index' => ['mappings' => []]]);
        $indices->expects($this->exactly(2))->method('putMapping');

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndices(['test_field' => ['type' => 'long']]);
    }

    public function testCreateFieldsInIndicesWithLanguagesDirectly(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('get')
            ->willReturn(['test_index' => ['mappings' => []]]);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with(static::callback(static function (array $params) {
                return isset($params['body']['properties']['customFields']['properties']['custom_lang']);
            }));

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndicesWithLanguages(
            ['field' => ['type' => 'boolean']],
            ['test_index'],
            ['custom_lang']
        );
    }

    public function testCreateFieldsInIndicesWithLanguagesEmptyFields(): void
    {
        $indexDetector = $this->createMock(ElasticsearchOutdatedIndexDetector::class);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('putMapping');

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($indices);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $helper = new ElasticsearchCustomFieldsMappingHelper($indexDetector, $client, $gateway);

        $helper->createFieldsInIndicesWithLanguages([], ['test_index'], ['lang1']);
    }
}
