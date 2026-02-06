<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Shopware\Core\System\CustomField\CustomFieldDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\CustomFieldSetGateway;
use Shopware\Elasticsearch\Product\CustomFieldUpdater;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;

// Covers also ElasticsearchCustomFieldsMappingHelper for mapping tests

/**
 * @internal
 */
#[CoversClass(CustomFieldUpdater::class)]
class CustomFieldUpdaterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            EntityWrittenContainerEvent::class => 'indexCustomFields',
        ], CustomFieldUpdater::getSubscribedEvents());
    }

    public function testNotProductWritten(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->expects($this->never())
            ->method('allowIndexing');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $this->createMock(CustomFieldSetGateway::class),
            $this->createMock(ElasticsearchCustomFieldsMappingHelper::class)
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection(),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testElasticsearchDisabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $this->createMock(CustomFieldSetGateway::class),
            $mappingHelper
        );

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldUpdatedChangesNothing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $this->createMock(CustomFieldSetGateway::class),
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult('test', ['name' => 'test', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, ['foo' => 'bar'], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldCreationDoesCreateThemInES(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        // For new fields, only check app-owned sets (not sorting/stream)
        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['test']) && $fields['test']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'test', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsAreNotIndexedWhenNonProductAssociationIsAddedToFieldSet(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldSetRelationId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult(
                $customFieldSetRelationId,
                ['entityName' => 'customer', 'customFieldSetId' => $customFieldSetId],
                CustomFieldSetRelationDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(CustomFieldSetRelationDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsAreIndexedWhenProductAssociationIsAddedToFieldSet(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldSetRelationId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        // First fetch candidate names from the set
        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesBySetIds')
            ->with([$customFieldSetId])
            ->willReturn(['field2']);

        // Then filter by candidates - first call returns empty, so second call gets remaining candidates
        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesUsedInProductSorting')
            ->with(['field2'])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesUsedInProductStream')
            ->with(['field2'])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchIndexableCustomFieldsForSets')
            ->with([$customFieldSetId], [], [])
            ->willReturn([$customFieldSetId => [
                ['id' => Uuid::randomHex(), 'name' => 'field2', 'type' => 'text'],
            ]]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['field2']) && $fields['field2']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult(
                $customFieldSetRelationId,
                ['entityName' => 'product', 'customFieldSetId' => $customFieldSetId],
                CustomFieldSetRelationDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(CustomFieldSetRelationDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testOnlyProductCustomFieldsAreCreatedInES(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId1 = Uuid::randomHex();
        $customFieldId2 = Uuid::randomHex();
        $customFieldSetId1 = Uuid::randomHex();
        $customFieldSetId2 = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId1, $customFieldId2])
            ->willReturn([$customFieldId1 => $customFieldSetId1, $customFieldId2 => $customFieldSetId2]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId1, $customFieldSetId2])
            ->willReturn([
                $customFieldSetId1 => ['customer'],
                $customFieldSetId2 => ['product', 'customer'],
            ]);

        // For new fields, only check app-owned sets (not sorting/stream)
        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                // Only field2 should be indexed (product-related)
                return isset($fields['field2'])
                    && !isset($fields['field1'])
                    && $fields['field2']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId1, ['name' => 'field1', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult($customFieldId2, ['name' => 'field2', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testNonSearchableCustomFieldsAreNotIndexedWhenCreated(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        // For new fields, only check app-owned sets - field doesn't meet criteria
        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'nonSearchableField', 'type' => 'text', 'includeInSearch' => false], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testSearchableCustomFieldsAreIndexedWhenCreated(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        // For new fields, only check app-owned sets
        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['searchableField']) && $fields['searchableField']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'searchableField', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testNonSearchableCustomFieldsAreNotIndexedWhenUpdated(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        // An update with includeInSearch=false and active not set should not trigger indexing
        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'nonSearchableField', 'type' => 'text', 'includeInSearch' => false], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testSearchableCustomFieldsAreIndexedWhenUpdated(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        // First fetch candidate names from the set
        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesBySetIds')
            ->with([$customFieldSetId])
            ->willReturn(['searchableField']);

        // Then filter by candidates
        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesUsedInProductSorting')
            ->with(['searchableField'])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchCustomFieldNamesUsedInProductStream')
            ->with(['searchableField'])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchIndexableCustomFieldsForSets')
            ->with([$customFieldSetId], [], [])
            ->willReturn([$customFieldSetId => [
                ['id' => $customFieldId, 'name' => 'searchableField', 'type' => 'text'],
            ]]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['searchableField']) && $fields['searchableField']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'searchableField', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testAppOwnedCustomFieldsAreIndexedWhenCreated(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        // Field belongs to app-owned set
        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId]); // This set is app-owned

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['appField']) && $fields['appField']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        // Field with includeInSearch=false but belongs to app-owned set
        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'appField', 'type' => 'text', 'includeInSearch' => false], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    /**
     * @param array<mixed> $mapping
     */
    #[DataProvider('providerMapping')]
    public function testMapping(string $type, array $mapping): void
    {
        // Test the helper method directly (CustomFieldUpdater::getTypeFromCustomFieldType is deprecated)
        static::assertSame($mapping, ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType($type));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<mixed>}>
     */
    public static function providerMapping(): iterable
    {
        yield 'int' => [
            CustomFieldTypes::INT,
            [
                'type' => 'long',
            ],
        ];

        yield 'float' => [
            CustomFieldTypes::FLOAT,
            [
                'type' => 'double',
            ],
        ];

        yield 'bool' => [
            CustomFieldTypes::BOOL,
            [
                'type' => 'boolean',
            ],
        ];

        yield 'datetime' => [
            CustomFieldTypes::DATETIME,
            [
                'type' => 'date',
                'format' => 'yyyy-MM-dd HH:mm:ss.SSS||strict_date_optional_time||epoch_millis',
                'ignore_malformed' => true,
            ],
        ];

        yield 'json' => [
            CustomFieldTypes::JSON,
            [
                'type' => 'object',
                'dynamic' => true,
            ],
        ];

        yield 'unknown' => [
            'unknown',
            [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                        'analyzer' => 'sw_whitespace_analyzer',
                    ],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
        ];
    }
}
