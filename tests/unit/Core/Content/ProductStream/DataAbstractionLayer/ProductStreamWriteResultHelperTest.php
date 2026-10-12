<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductStream\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterDefinition;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamWriteResultHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
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
     * A real DAL write never exposes the owning stream on its own: a delete carries only the primary
     * key, and a partial update only the changed fields. The existence state is no help either, since
     * `EntityWriteGateway` fills it with nothing but `exists`, the parent field and the primary key.
     * The change set's previous row state is the only place left to read the stream from.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $previousState
     */
    #[DataProvider('changeSetProvider')]
    public function testCollectsStreamIdFromChangeSet(string $operation, array $payload, array $previousState): void
    {
        $streamId = Uuid::randomHex();

        $event = $this->buildContainerEvent([
            new EntityWriteResult(
                Uuid::randomHex(),
                $payload,
                ProductStreamFilterDefinition::ENTITY_NAME,
                $operation,
                null,
                new ChangeSet(
                    array_merge(['product_stream_id' => Uuid::fromHexToBytes($streamId)], $previousState),
                    $payload,
                    isDelete: $operation === EntityWriteResult::OPERATION_DELETE,
                ),
            ),
        ]);

        static::assertSame([$streamId], ProductStreamWriteResultHelper::getAffectedStreamIds($event));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, array<string, mixed>}>
     */
    public static function changeSetProvider(): iterable
    {
        yield 'delete sends only the primary key, issue #16994' => [
            EntityWriteResult::OPERATION_DELETE,
            [],
            [],
        ];

        yield 'partial update sends only the changed fields, issue #18680' => [
            EntityWriteResult::OPERATION_UPDATE,
            ['value' => '5'],
            ['value' => '3'],
        ];
    }

    /**
     * Regression: when a filter is reassigned from stream A to stream B, the payload exposes B and
     * the change set's previous row state holds A. Both must be invalidated so that neither stream
     * is left with a stale `api_filter` and mapping.
     *
     * The existence state cannot carry the old id here: `EntityWriteGateway` selects only
     * `1 as exists`, the parent field and the primary key, and otherwise merges the queued command
     * payload, which for a reassignment already holds the new stream.
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
                null,
                new ChangeSet(
                    ['product_stream_id' => Uuid::fromHexToBytes($oldStreamId)],
                    ['product_stream_id' => Uuid::fromHexToBytes($newStreamId)],
                    isDelete: false,
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
