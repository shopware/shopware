<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
    /**
     * @deprecated tag:v6.5.0 - does not need to run in seperate service anymore
     *
     * @runInSeparateProcess
     */
    public function testSyncSingleOperation(): void
    {
        $_SERVER['v6_5_0_0'] = '1';
        $_SERVER['FEATURE_NEXT_15815'] = '1';

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
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(Connection::class),
            $this->createMock(ApiVersionConverter::class),
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
