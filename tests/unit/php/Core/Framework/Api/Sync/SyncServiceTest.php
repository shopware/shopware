<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\Sync\SyncService
 */
class SyncServiceTest extends TestCase
{
    public function testSyncSingleOperation(): void
    {
        $writeResult = new WriteResult(
            [
                [new EntityWriteResult('deleted-id', [], 'product', EntityWriteResult::OPERATION_DELETE)],
            ],
            [],
            [
                [new EntityWriteResult('created-id', [], 'product', EntityWriteResult::OPERATION_INSERT)],
            ]
        );

        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects(static::once())
            ->method('sync')
            ->willReturn($writeResult);

        $service = new SyncService(
            $writer,
            $this->createMock(EventDispatcherInterface::class),
        );

        $upsert = new SyncOperation('foo', 'product', SyncOperation::ACTION_UPSERT, [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
            ['id' => '2'],
        ]);

        $behavior = new SyncBehavior('disable-indexing', ['product.indexer']);
        $result = $service->sync([$upsert, $delete], Context::createDefaultContext(), $behavior);

        static::assertSame([
            'product' => [
                'deleted-id',
            ],
        ], $result->getDeleted());

        static::assertSame([
            [
                'created-id',
            ],
        ], $result->getData());

        static::assertSame([], $result->getNotFound());
    }
}
