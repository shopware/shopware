<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Shopware\Elasticsearch\Product\ProductCustomFieldsUsedUpdater;

/**
 * @internal
 */
#[CoversClass(ProductCustomFieldsUsedUpdater::class)]
class ProductCustomFieldsUsedUpdaterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            ProductSortingDefinition::ENTITY_NAME . '.written' => 'productSortingWritten',
            ProductStreamFilterDefinition::ENTITY_NAME . '.written' => 'productStreamFilterWritten',
        ], ProductCustomFieldsUsedUpdater::getSubscribedEvents());
    }

    public function testProductSortingNoActionWhenElasticsearchDisabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductStreamNoActionWhenElasticsearchDisabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                ['name' => 'Test Stream'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.test_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'product.name', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'test_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['test_field']) && $fields['test_field']['type'] === 'long';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductSortingWithoutFieldsInPayloadDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductStreamFilterWithCustomFieldTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['stream_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'stream_field' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['stream_field']) && $fields['stream_field']['type'] === 'keyword';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId,
                ['name' => 'Test Stream Filter'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testMultipleProductStreamFiltersAreProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId1 = Uuid::randomHex();
        $productStreamFilterId2 = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['field1', 'field2']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'field1' => 'int',
                'field2' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId1,
                ['name' => 'Stream Filter 1'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
            new EntityWriteResult(
                $productStreamFilterId2,
                ['name' => 'Stream Filter 2'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testNoCustomFieldsInSortingDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
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
                        ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                        ['field' => 'product.price', 'order' => 'desc', 'priority' => 0, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testProductStreamFilterWithNoCustomFieldsDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Returns empty array - no custom fields found
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId,
                ['name' => 'Test Stream Filter'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.field1', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
                json_encode([
                    ['field' => 'customFields.field2', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'field1' => 'int',
                'field2' => 'bool',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['field1']) && $fields['field1']['type'] === 'long'
                    && isset($fields['field2']) && $fields['field2']['type'] === 'boolean';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.updated_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'updated_field' => 'float',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['updated_field']) && $fields['updated_field']['type'] === 'double';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.nonexistent_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        // Returns empty - custom field not found in database
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductStreamFilterUpdateWithFieldChange(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['new_filter_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'new_filter_field' => 'datetime',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['new_filter_field'])
                    && $fields['new_filter_field']['type'] === 'date';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId,
                ['name' => 'Updated Stream Filter'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testProductStreamWrittenWithNoCustomFieldFilters(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId,
                ['name' => 'Stream Filter without custom fields'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testProductSortingAndStreamEventsProcessedIndependently(): void
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
                [
                    json_encode([
                        ['field' => 'customFields.sorting_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ]),
                ],
                ['filter_field']
            );

        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllKeyValue')
            ->willReturnOnConsecutiveCalls(
                ['sorting_field' => 'int'],
                ['filter_field' => 'text']
            );

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->exactly(2))->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        // Process product sorting event
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

        $sortingEvent = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $sortingWriteResults, Context::createDefaultContext());
        $updater->productSortingWritten($sortingEvent);

        $results = [
            new EntityWriteResult(
                Uuid::randomHex(),
                ['name' => 'Stream Filter with custom field'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $results, Context::createDefaultContext());
        $updater->productStreamFilterWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.same_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'customFields.same_field', 'order' => 'desc', 'priority' => 0, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'same_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return \count($fields) === 1 && isset($fields['same_field']);
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductSortingWithFieldsTriggersIndexing(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.combined_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'combined_field' => 'bool',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['combined_field']) && $fields['combined_field']['type'] === 'boolean';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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
                        ['field' => 'customFields.combined_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testProductSortingDeleteOperationIsProcessed(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testProductStreamFilterDeleteOperationIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testProductSortingWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testProductStreamWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.int_field', 'order' => 'asc', 'priority' => 5, 'naturalSorting' => false],
                    ['field' => 'customFields.float_field', 'order' => 'asc', 'priority' => 4, 'naturalSorting' => false],
                    ['field' => 'customFields.bool_field', 'order' => 'asc', 'priority' => 3, 'naturalSorting' => false],
                    ['field' => 'customFields.datetime_field', 'order' => 'asc', 'priority' => 2, 'naturalSorting' => false],
                    ['field' => 'customFields.json_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'int_field' => 'int',
                'float_field' => 'float',
                'bool_field' => 'bool',
                'datetime_field' => 'datetime',
                'json_field' => 'json',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['int_field']) && $fields['int_field']['type'] === 'long'
                    && isset($fields['float_field']) && $fields['float_field']['type'] === 'double'
                    && isset($fields['bool_field']) && $fields['bool_field']['type'] === 'boolean'
                    && isset($fields['datetime_field']) && $fields['datetime_field']['type'] === 'date'
                    && isset($fields['json_field']) && $fields['json_field']['type'] === 'object';
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
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
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.sorting_only_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'sorting_only_field' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
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

        $updater->productSortingWritten($event);
    }

    public function testOnlyProductStreamEventWithNoSorting(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $productStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['filter_only_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'filter_only_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                $productStreamFilterId,
                ['name' => 'Filter only stream filter'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testProductSortingWithNonStringPrimaryKeyIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                ['id' => Uuid::randomHex(), 'versionId' => Uuid::randomHex()],
                [
                    'fields' => [
                        ['field' => 'customFields.test_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testProductStreamFilterWithNonStringPrimaryKeyIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $this->createMock(Connection::class)
        );

        $writeResults = [
            new EntityWriteResult(
                ['id' => Uuid::randomHex(), 'versionId' => Uuid::randomHex()],
                ['name' => 'Test Filter'],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productStreamFilterWritten($event);
    }

    public function testSortingWithInvalidJsonFromDatabaseIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                'not-valid-json{{{',
                json_encode([
                    ['field' => 'customFields.valid_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'valid_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return \count($fields) === 1 && isset($fields['valid_field']);
            }));

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'fields' => [
                        ['field' => 'customFields.valid_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testSortingWithNonArrayJsonFromDatabaseIsSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                '"just a string"',
                '42',
                'null',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'fields' => [
                        ['field' => 'customFields.some_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }

    public function testSortingFieldsWithoutFieldKeyAreSkipped(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['order' => 'asc', 'priority' => 1],
                    ['field' => 123, 'order' => 'asc', 'priority' => 2],
                    ['field' => 'product.name', 'order' => 'asc', 'priority' => 3],
                ]),
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new ProductCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
            new EntityWriteResult(
                Uuid::randomHex(),
                [
                    'fields' => [
                        ['field' => 'product.name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ],
                ],
                ProductSortingDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
        ];

        $event = new EntityWrittenEvent(ProductSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->productSortingWritten($event);
    }
}
