<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncOperationResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * The test has to run in an separate process as SyncBehavior class has an if condition around the entire class and we want to test the other behaviour
 * @runInSeparateProcess
 *
 * @deprecated tag:v6.5.0 - Only covers deprecated parts
 *
 * @covers \Shopware\Core\Framework\Api\Sync\SyncService
 */
class SyncServiceDeprecatedTest extends TestCase
{
    public function testSyncSingleOperation(): void
    {
        $_SERVER = $_ENV = [];
        $_SERVER['v6_5_0_0'] = '1';
        $_SERVER['FEATURE_NEXT_15815'] = '0';

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

        $behavior = new SyncBehavior(true, true, 'disable-indexing', ['product.indexer']);
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

        static::assertTrue($result->isSuccess());
    }

    /**
     * @deprecated tag:v6.5.0 - Remove test when the deprecated behavior is removed
     */
    public function testSyncBatch(): void
    {
        $_SERVER = $_ENV = [];

        $writeResult = new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('product', [new EntityWriteResult('created-id', [], 'product', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
        ]), []);

        $deleteResult = new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('product', [new EntityWriteResult('deleted-id', [], 'product', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
        ]), []);

        $entityRepo = $this->createMock(EntityRepository::class);
        $entityRepo
            ->method('upsert')
            ->willReturn($writeResult);

        $entityRepo
            ->method('delete')
            ->willReturn($deleteResult);

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry
            ->method('getRepository')
            ->willReturn($entityRepo);

        $apiVersionConverter = $this->createMock(ApiVersionConverter::class);
        $apiVersionConverter->method('convertPayload')->willReturnArgument(1);

        $service = new SyncService(
            $definitionRegistry,
            $this->createMock(Connection::class),
            $apiVersionConverter,
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $upsert = new SyncOperation('foo', 'product', SyncOperation::ACTION_UPSERT, [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
        ]);

        $result = $service->sync([$upsert, $delete], Context::createDefaultContext(), new SyncBehavior(true, false, 'disable-indexing', ['product.indexer']));

        $data = $result->getData();

        static::assertArrayHasKey('foo', $data);
        static::assertArrayHasKey('delete-foo', $data);

        $foo = $data['foo'];
        static::assertInstanceOf(SyncOperationResult::class, $foo);

        static::assertCount(2, $foo->getResult());

        static::assertSame([
            'entities' => [
                'product' => [
                    'created-id',
                ],
            ],
            'errors' => [],
        ], $foo->getResult()[0]);

        $deleteOp = $data['delete-foo'];
        static::assertInstanceOf(SyncOperationResult::class, $deleteOp);

        static::assertCount(1, $deleteOp->getResult());

        static::assertSame([
            'entities' => [
                'product' => [
                    'deleted-id',
                ],
            ],
            'errors' => [],
        ], $deleteOp->getResult()[0]);

        static::assertTrue($result->isSuccess());
    }

    public function testSyncWithInvalidOperation(): void
    {
        $_SERVER = $_ENV = [];

        $service = new SyncService(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(Connection::class),
            $this->createMock(ApiVersionConverter::class),
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $upsert = new SyncOperation('foo', 'product', 'uff', [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('provided action uff is not supported. Following actions are supported: delete, upsert');

        $service->sync([$upsert], Context::createDefaultContext(), new SyncBehavior(true, false, 'disable-indexing', ['product.indexer']));
    }

    /**
     * @deprecated tag:v6.5.0 - Remove test when the deprecated behavior is removed
     */
    public function testSyncBatchFails(): void
    {
        $_SERVER = $_ENV = [];

        $entityRepo = $this->createMock(EntityRepository::class);
        $writeException = new WriteException();
        $constraintViolation = new ConstraintViolation('test', 'test', [], '', '', '');

        $writeException->add(new WriteConstraintViolationException(new ConstraintViolationList([$constraintViolation])));

        $entityRepo
            ->method('upsert')
            ->willThrowException($writeException);

        $entityRepo
            ->method('delete')
            ->willThrowException(new \RuntimeException('delete failed'));

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry
            ->method('getRepository')
            ->willReturn($entityRepo);

        $apiVersionConverter = $this->createMock(ApiVersionConverter::class);
        $apiVersionConverter->method('convertPayload')->willReturnArgument(1);

        $service = new SyncService(
            $definitionRegistry,
            $this->createMock(Connection::class),
            $apiVersionConverter,
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $upsert = new SyncOperation('foo', 'product', SyncOperation::ACTION_UPSERT, [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
        ]);

        $result = $service->sync([$upsert, $delete], Context::createDefaultContext(), new SyncBehavior(true, false, 'disable-indexing', ['product.indexer']));

        $data = $result->getData();

        static::assertArrayHasKey('foo', $data);
        static::assertArrayHasKey('delete-foo', $data);

        $foo = $data['foo'];
        static::assertInstanceOf(SyncOperationResult::class, $foo);

        static::assertCount(2, $foo->getResult());

        static::assertSame([
            'entities' => [],
            'errors' => [
                [
                    'code' => 'FRAMEWORK__WRITE_CONSTRAINT_VIOLATION',
                    'status' => '400',
                    'detail' => 'test',
                    'template' => 'test',
                    'meta' => [
                        'parameters' => [],
                    ],
                    'source' => [
                        'pointer' => '',
                    ],
                ],
            ],
        ], $foo->getResult()[0]);

        $deleteOp = $data['delete-foo'];
        static::assertInstanceOf(SyncOperationResult::class, $deleteOp);

        static::assertCount(1, $deleteOp->getResult());

        static::assertSame([
            'entities' => [],
            'errors' => [
                ['code' => '0',
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => 'delete failed', ],
            ],
        ], $deleteOp->getResult()[0]);

        static::assertFalse($result->isSuccess());
    }

    public function testFailRollbacksDatabase(): void
    {
        $_SERVER = $_ENV = [];

        $entityRepo = $this->createMock(EntityRepository::class);

        $entityRepo
            ->method('delete')
            ->willThrowException(new \RuntimeException('foo'));

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry
            ->method('getRepository')
            ->willReturn($entityRepo);

        $apiVersionConverter = $this->createMock(ApiVersionConverter::class);
        $apiVersionConverter->method('convertPayload')->willReturnArgument(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');
        $connection->expects(static::once())->method('rollBack');

        $service = new SyncService(
            $definitionRegistry,
            $connection,
            $apiVersionConverter,
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
        ]);

        $behavior = new SyncBehavior(false, false, 'disable-indexing', ['product.indexer']);
        $result = $service->sync([$delete], Context::createDefaultContext(), $behavior);

        static::assertFalse($result->isSuccess());
    }

    /**
     * @dataProvider providerTransactionModes
     */
    public function testTransactionGetsCommitted(bool $fail): void
    {
        $_SERVER = $_ENV = [];

        $deleteResult = new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('product', [new EntityWriteResult('deleted-id', [], 'product', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
        ]), []);

        $entityRepo = $this->createMock(EntityRepository::class);

        $entityRepo
            ->method('delete')
            ->willReturn($deleteResult);

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry
            ->method('getRepository')
            ->willReturn($entityRepo);

        $apiVersionConverter = $this->createMock(ApiVersionConverter::class);
        $apiVersionConverter->method('convertPayload')->willReturnArgument(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('beginTransaction');

        if ($fail) {
            $connection->method('isRollbackOnly')->willReturn(true);
            $connection->expects(static::once())->method('rollBack');
        } else {
            $connection->expects(static::once())->method('commit');
        }

        $service = new SyncService(
            $definitionRegistry,
            $connection,
            $apiVersionConverter,
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EventDispatcherInterface::class),
        );

        $delete = new SyncOperation('delete-foo', 'product', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
        ]);

        $behavior = new SyncBehavior(false, false, 'disable-indexing', ['product.indexer']);
        $result = $service->sync([$delete], Context::createDefaultContext(), $behavior);

        static::assertTrue($result->isSuccess());
    }

    /**
     * @return iterable<bool[]>
     */
    public function providerTransactionModes(): iterable
    {
        yield 'transaction works' => [
            false,
        ];

        yield 'transaction dont work and should rollback' => [
            true,
        ];
    }
}
