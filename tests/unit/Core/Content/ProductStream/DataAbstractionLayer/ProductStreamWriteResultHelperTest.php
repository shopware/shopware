<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamWriteResultHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductStreamWriteResultHelper::class)]
class ProductStreamWriteResultHelperTest extends TestCase
{
    public function testReturnsEmptyWhenContainerHasNoFilterEvent(): void
    {
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([]),
            [],
        );

        static::assertSame([], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    public function testReturnsEmptyWhenEventIsNull(): void
    {
        static::assertSame([], ProductStreamWriteResultHelper::getAffectedStreamIdsFromEvent(null));
    }

    public function testCollectsStreamIdFromPayloadCamelCase(): void
    {
        $streamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                ['productStreamId' => $streamId],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ]);

        static::assertSame([$streamId], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    public function testCollectsStreamIdFromPayloadSnakeCase(): void
    {
        $streamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                ['product_stream_id' => $streamId],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ]);

        static::assertSame([$streamId], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    public function testCollectsStreamIdFromExistenceStateOnDelete(): void
    {
        $streamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                [],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE,
                new EntityExistence(
                    ProductStreamFilterDefinition::ENTITY_NAME,
                    ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                    true,
                    false,
                    false,
                    ['product_stream_id' => Uuid::fromHexToBytes($streamId)],
                ),
            ),
        ]);

        static::assertSame([$streamId], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    /**
     * Regression: when a filter is reassigned from stream A to stream B, the payload
     * exposes B and the existence state still holds A. Both must be invalidated so that
     * neither stream's cached `api_filter` is left stale.
     */
    public function testReassignmentReturnsBothOldAndNewStreamIds(): void
    {
        $oldStreamId = Uuid::randomHex();
        $newStreamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                ['productStreamId' => $newStreamId],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
                new EntityExistence(
                    ProductStreamFilterDefinition::ENTITY_NAME,
                    ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                    true,
                    false,
                    false,
                    ['product_stream_id' => Uuid::fromHexToBytes($oldStreamId)],
                ),
            ),
        ]);

        $ids = ProductStreamWriteResultHelper::getAffectedStreamIds($event);

        static::assertCount(2, $ids);
        static::assertContains($oldStreamId, $ids);
        static::assertContains($newStreamId, $ids);
    }

    public function testDeduplicatesAcrossMultipleWriteResults(): void
    {
        $streamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                ['productStreamId' => $streamId],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
            new EntityWriteResult(
                Uuid::randomHex(),
                ['productStreamId' => $streamId],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
            new EntityWriteResult(
                Uuid::randomHex(),
                [],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE,
                new EntityExistence(
                    ProductStreamFilterDefinition::ENTITY_NAME,
                    ['id' => Uuid::fromHexToBytes(Uuid::randomHex())],
                    true,
                    false,
                    false,
                    ['product_stream_id' => Uuid::fromHexToBytes($streamId)],
                ),
            ),
        ]);

        static::assertSame([$streamId], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    public function testIgnoresWriteResultsWithoutAnyStreamId(): void
    {
        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                [],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ]);

        static::assertSame([], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    public function testIgnoresEmptyStringStreamId(): void
    {
        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                ['productStreamId' => ''],
                ProductStreamFilterDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ]);

        static::assertSame([], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    /**
     * @param list<EntityWriteResult> $writeResults
     */
    private function buildContainerEvent(array $writeResults): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    ProductStreamFilterDefinition::ENTITY_NAME,
                    $writeResults,
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        );
    }
}
