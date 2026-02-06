<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Shopware\Elasticsearch\Product\ProductSortingStreamUpdater;

/**
 * @internal
 */
#[CoversClass(ProductSortingStreamUpdater::class)]
class ProductSortingStreamUpdaterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ], ProductSortingStreamUpdater::getSubscribedEvents());
    }

    public function testNoActionWhenNoRelevantEvents(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->expects($this->never())
            ->method('allowIndexing');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $this->createMock(ElasticsearchCustomFieldsMappingHelper::class),
            $this->createMock(Connection::class)
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection(),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testNoActionWhenElasticsearchDisabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.test_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithCustomFieldTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['test_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'test_field', 'type' => 'int'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['test_field']) && $fields['test_field']['type'] === 'long';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.test_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                        ['field' => 'product.name', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithoutFieldsInPayloadDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        // Payload without 'fields' - e.g., only updating priority
        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 2,
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterWithCustomFieldTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['stream_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'stream_field', 'type' => 'text'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['stream_field']) && $fields['stream_field']['type'] === 'keyword';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [
                    'field' => 'customFields.stream_field',
                    'type' => 'equals',
                    'value' => 'test',
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testMultipleProductStreamFiltersAreProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['field1', 'field2']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'field1', 'type' => 'int'],
                ['name' => 'field2', 'type' => 'text'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId1,
                ['field' => 'customFields.field1', 'type' => 'equals', 'value' => 1],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
            new EntityWriteResult(
                $filterId2,
                ['field' => 'customFields.field2', 'type' => 'contains', 'value' => 'test'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testNoCustomFieldsInSortingDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                        ['field' => 'product.price', 'order' => 'desc', 'priority' => 0, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterWithNoCustomFieldsDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Returns empty array - no custom fields found
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [
                    'field' => 'product.name',
                    'type' => 'equals',
                    'value' => 'test',
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testMultipleProductSortingsAreProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['field1', 'field2']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'field1', 'type' => 'int'],
                ['name' => 'field2', 'type' => 'bool'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['field1']) && $fields['field1']['type'] === 'long'
                    && isset($fields['field2']) && $fields['field2']['type'] === 'boolean';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'sorting-1',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.field1', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'sorting-2',
                    'priority' => 2,
                    'fields' => [
                        ['field' => 'customFields.field2', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingUpdateOperationTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['updated_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'updated_field', 'type' => 'float'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['updated_field']) && $fields['updated_field']['type'] === 'double';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'fields' => [
                        ['field' => 'customFields.updated_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testCustomFieldNotFoundInDatabaseDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['nonexistent_field']);

        // Returns empty - custom field not found in database
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.nonexistent_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterUpdateWithFieldChange(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['new_filter_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'new_filter_field', 'type' => 'datetime'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['new_filter_field'])
                    && $fields['new_filter_field']['type'] === 'date';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [
                    'field' => 'customFields.new_filter_field',
                    'type' => 'range',
                    'value' => '2024-01-01',
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterWithoutFieldInPayloadDoesNotQueryDatabase(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // fetchFirstColumn is still called but for filter IDs query
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        // Payload without 'field' - e.g., only updating value
        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [
                    'type' => 'equals',
                    'value' => 'updated_value',
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testBothProductSortingAndStreamFilterInSameEvent(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls(
                ['sorting_field'],
                ['filter_field']
            );

        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [['name' => 'sorting_field', 'type' => 'int']],
                [['name' => 'filter_field', 'type' => 'text']]
            );

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->exactly(2))->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $sortingWriteResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.sorting_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $filterWriteResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'field' => 'customFields.filter_field',
                    'type' => 'equals',
                    'value' => 'test',
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $sortingEvent = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $sortingWriteResults, Context::createDefaultContext());
        $filterEvent = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $filterWriteResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$sortingEvent, $filterEvent]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testDuplicateCustomFieldsAreDeduplicatedInSorting(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['same_field', 'same_field']); // DB returns duplicates

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'same_field', 'type' => 'int'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return \count($fields) === 1 && isset($fields['same_field']);
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.same_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                        ['field' => 'customFields.same_field', 'order' => 'desc', 'priority' => 0, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithActiveSetToTrueTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['active_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'active_field', 'type' => 'text'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        // Payload without 'fields' but with 'active' = true should still trigger query
        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'active' => true,
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithActiveSetToFalseDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        // Payload without 'fields' and with 'active' = false should not trigger
        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'active' => false,
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithFieldsAndActiveTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['combined_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'combined_field', 'type' => 'bool'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['combined_field']) && $fields['combined_field']['type'] === 'boolean';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        // Payload with both 'fields' and 'active' should trigger
        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'active' => true,
                    'fields' => [
                        ['field' => 'customFields.combined_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingDeleteOperationIsProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterDeleteOperation(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Still collects filter IDs, but query may return empty for deleted records
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        // Empty write results
        $writeResults = [];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductStreamFilterWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        // Empty write results
        $writeResults = [];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testProductSortingWithMixedFieldTypes(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['int_field', 'float_field', 'bool_field', 'datetime_field', 'json_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'int_field', 'type' => 'int'],
                ['name' => 'float_field', 'type' => 'float'],
                ['name' => 'bool_field', 'type' => 'bool'],
                ['name' => 'datetime_field', 'type' => 'datetime'],
                ['name' => 'json_field', 'type' => 'json'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(function (array $fields) {
                return isset($fields['int_field']) && $fields['int_field']['type'] === 'long'
                    && isset($fields['float_field']) && $fields['float_field']['type'] === 'double'
                    && isset($fields['bool_field']) && $fields['bool_field']['type'] === 'boolean'
                    && isset($fields['datetime_field']) && $fields['datetime_field']['type'] === 'date'
                    && isset($fields['json_field']) && $fields['json_field']['type'] === 'object';
            }));

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'test-sorting',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.int_field', 'order' => 'asc', 'priority' => 5, 'naturalSorting' => false],
                        ['field' => 'customFields.float_field', 'order' => 'asc', 'priority' => 4, 'naturalSorting' => false],
                        ['field' => 'customFields.bool_field', 'order' => 'asc', 'priority' => 3, 'naturalSorting' => false],
                        ['field' => 'customFields.datetime_field', 'order' => 'asc', 'priority' => 2, 'naturalSorting' => false],
                        ['field' => 'customFields.json_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testOnlyProductSortingEventWithNoFilters(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['sorting_only_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'sorting_only_field', 'type' => 'text'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'urlKey' => 'sorting-only',
                    'priority' => 1,
                    'fields' => [
                        ['field' => 'customFields.sorting_only_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        // Only product sorting event, no stream filter event
        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }

    public function testOnlyProductStreamFilterEventWithNoSorting(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $filterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['filter_only_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['name' => 'filter_only_field', 'type' => 'int'],
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductSortingStreamUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $filterId,
                [
                    'field' => 'customFields.filter_only_field',
                    'type' => 'equals',
                    'value' => 123,
                ],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        // Only stream filter event, no product sorting event
        $containerEvent = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $updater->onEntityWritten($containerEvent);
    }
}
