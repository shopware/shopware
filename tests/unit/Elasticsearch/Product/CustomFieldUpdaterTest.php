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

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
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

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => [
                ['id' => Uuid::randomHex(), 'name' => 'field2', 'type' => 'text'],
            ]]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
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

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId1, $customFieldSetId2])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                // Only field2 should be indexed (product-related, includeInSearch)
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

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
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

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => [
                ['id' => $customFieldId, 'name' => 'searchableField', 'type' => 'text'],
            ]]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
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

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['appField']) && $fields['appField']['type'] === 'keyword';
            }));

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        // App-owned set: include all fields even without includeInSearch (app manifest does not set it)
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

    public function testExistingRelationIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $customFieldSetRelationId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        // Existing relation (existence->exists() returns true)
        $writeResults = [
            new EntityWriteResult(
                $customFieldSetRelationId,
                ['entityName' => 'product', 'customFieldSetId' => $customFieldSetId],
                CustomFieldSetRelationDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
                new EntityExistence(null, [], true, false, false, []) // exists = true
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

    public function testCustomFieldRelationWithNoIncludeInSearchFieldsReturnsEmpty(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldSetRelationId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with([]);

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

    public function testCustomFieldRelationCallsFetchCustomFieldsForSets(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldSetRelationId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

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

    public function testCustomFieldCreatedWithNoSetIdMapping(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([]); // No set ID found

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'orphanField', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldCreatedWithoutIncludeInSearchKey(): void
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
            new EntityWriteResult($customFieldId, ['name' => 'field', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsUpdatedWithNoPropertyChange(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();

        // No fetchFieldSetIds call expected since customFieldIds is empty (no property change)

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        // Update with 'name' change, not 'includeInSearch'
        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'newName', 'type' => 'text'], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        // No 'includeInSearch' in primaryKeysWithPropertyChange
        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsUpdatedWithKeyNotInCustomFieldIds(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId1 = Uuid::randomHex();
        $customFieldId2 = Uuid::randomHex();

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        // Write result key doesn't match getPrimaryKeysWithPropertyChange
        $writeResults = [
            new EntityWriteResult($customFieldId1, ['name' => 'field', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        // Different ID in propertyChange
        $containerEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $containerEvent->method('getEventByEntityName')
            ->willReturnCallback(static function (string $entityName) use ($event) {
                if ($entityName === CustomFieldDefinition::ENTITY_NAME) {
                    return $event;
                }

                return null;
            });
        $containerEvent->method('getPrimaryKeysWithPropertyChange')
            ->willReturn([$customFieldId2]); // Different ID

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsUpdatedWithNewRecord(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);
        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();

        // For created records (existence is null or doesn't exist), customFieldsUpdated should skip
        // and only customFieldsCreated should process

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'newField', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $containerEvent->method('getEventByEntityName')
            ->willReturnCallback(static function (string $entityName) use ($event) {
                if ($entityName === CustomFieldDefinition::ENTITY_NAME) {
                    return $event;
                }

                return null;
            });
        $containerEvent->method('getPrimaryKeysWithPropertyChange')
            ->willReturn([$customFieldId]);

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsUpdatedWithEmptyFieldSetIds(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([]); // Empty result

        // Should not continue after empty fieldSetIds
        $gateway->expects($this->never())->method('fetchFieldSetEntityMappings');

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'field', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $containerEvent->method('getEventByEntityName')
            ->willReturnCallback(static function (string $entityName) use ($event) {
                if ($entityName === CustomFieldDefinition::ENTITY_NAME) {
                    return $event;
                }

                return null;
            });
        $containerEvent->method('getPrimaryKeysWithPropertyChange')
            ->willReturn([$customFieldId]);

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testCustomFieldsUpdatedNotRelatedToProduct(): void
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
            ->willReturn([$customFieldSetId => ['customer']]); // Not product!

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => [
                ['id' => $customFieldId, 'name' => 'fieldName', 'type' => 'text'],
            ]]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())
            ->method('createFieldsInIndices')
            ->with([]);

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $writeResults = [
            new EntityWriteResult($customFieldId, ['name' => 'fieldName', 'type' => 'text', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE, new EntityExistence(null, [], true, false, false, [])),
        ];

        $event = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $containerEvent->method('getEventByEntityName')
            ->willReturnCallback(static function (string $entityName) use ($event) {
                if ($entityName === CustomFieldDefinition::ENTITY_NAME) {
                    return $event;
                }

                return null;
            });
        $containerEvent->method('getPrimaryKeysWithPropertyChange')
            ->willReturn([$customFieldId]);

        $customFieldUpdater->indexCustomFields($containerEvent);
    }

    public function testBothCustomFieldAndRelationEventsProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $gateway = $this->createMock(CustomFieldSetGateway::class);

        $customFieldId = Uuid::randomHex();
        $customFieldSetId = Uuid::randomHex();
        $relationId = Uuid::randomHex();
        $relationSetId = Uuid::randomHex();

        $gateway->expects($this->once())
            ->method('fetchCustomFieldsForSets')
            ->with([$relationSetId])
            ->willReturn([]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetIds')
            ->with([$customFieldId])
            ->willReturn([$customFieldId => $customFieldSetId]);

        $gateway->expects($this->once())
            ->method('fetchFieldSetEntityMappings')
            ->with([$customFieldSetId])
            ->willReturn([$customFieldSetId => ['product']]);

        $gateway->expects($this->once())
            ->method('fetchAppOwnedFieldSetIds')
            ->with([$customFieldSetId])
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->exactly(2))->method('createFieldsInIndices');

        $customFieldUpdater = new CustomFieldUpdater(
            $elasticsearchHelper,
            $gateway,
            $mappingHelper
        );

        $relationWriteResults = [
            new EntityWriteResult(
                $relationId,
                ['entityName' => 'product', 'customFieldSetId' => $relationSetId],
                CustomFieldSetRelationDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $customFieldWriteResults = [
            new EntityWriteResult($customFieldId, ['name' => 'newField', 'type' => 'int', 'includeInSearch' => true], CustomFieldDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ];

        $relationEvent = new EntityWrittenEvent(CustomFieldSetRelationDefinition::ENTITY_NAME, $relationWriteResults, Context::createDefaultContext());
        $customFieldEvent = new EntityWrittenEvent(CustomFieldDefinition::ENTITY_NAME, $customFieldWriteResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$relationEvent, $customFieldEvent]),
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

    // write test suite for getTypeFromCustomFieldType
    public function testGetTypeFromCustomFieldType(): void
    {
        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType(CustomFieldTypes::INT);
        static::assertEquals(['type' => 'long'], $result);

        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType(CustomFieldTypes::FLOAT);
        static::assertEquals(['type' => 'double'], $result);

        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType(CustomFieldTypes::BOOL);
        static::assertEquals(['type' => 'boolean'], $result);

        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType(CustomFieldTypes::DATETIME);
        static::assertEquals(['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss.SSS||strict_date_optional_time||epoch_millis', 'ignore_malformed' => true], $result);

        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType(CustomFieldTypes::JSON);
        static::assertEquals(['type' => 'object', 'dynamic' => true], $result);

        $result = ElasticsearchCustomFieldsMappingHelper::getTypeFromCustomFieldType('unknown');
        static::assertEquals(['type' => 'keyword', 'ignore_above' => 10000, 'normalizer' => 'sw_lowercase_normalizer', 'fields' => ['search' => ['type' => 'text', 'analyzer' => 'sw_whitespace_analyzer'], 'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer']]], $result);
    }
}
