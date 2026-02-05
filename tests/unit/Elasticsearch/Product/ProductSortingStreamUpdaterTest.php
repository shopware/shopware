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
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

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
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

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
}
