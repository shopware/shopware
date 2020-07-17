<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Api\Converter\ConverterRegistry;
use Shopware\Core\Framework\Api\Converter\DefaultApiConverter;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncOperationResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedConverter;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedDefinition;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedEntityDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

class SyncServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SyncService
     */
    private $service;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getContainer()->get(SyncService::class);
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testSingleOperation(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operation = new SyncOperation(
            'write',
            'product_manufacturer',
            SyncOperation::ACTION_UPSERT,
            [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2, 'name' => 'second manufacturer'],
            ],
            PlatformRequest::API_VERSION
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

        static::assertTrue($result->isSuccess());
        $operation = $result->get('write');

        static::assertInstanceOf(SyncOperationResult::class, $operation);
        static::assertFalse($operation->hasError());

        static::assertTrue($operation->has(0));
        static::assertTrue($operation->has(1));

        $written = $operation->get(0);
        static::assertEquals([], $written['errors']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);

        $written = $operation->get(1);
        static::assertEquals([], $written['errors']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);
    }

    public function testSingleOperationWithDeletesAndWrites(): void
    {
        $ids = new TestDataCollection();

        $operations = [
            new SyncOperation('write', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m1'), 'name' => 'first manufacturer'],
                ['id' => $ids->create('m2'), 'name' => 'second manufacturer'],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('write', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('t1'), 'name' => 'first tax', 'taxRate' => 10],
                ['id' => $ids->create('t2'), 'name' => 'second tax', 'taxRate' => 10],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('write', 'country', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('c1'), 'name' => 'first country'],
                ['id' => $ids->create('c2'), 'name' => 'second country'],
            ], PlatformRequest::API_VERSION),
        ];

        $behavior = new SyncBehavior(false, true);

        $this->service->sync($operations, Context::createDefaultContext(), $behavior);

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this
            ->getMockBuilder(CallableClass::class)
            ->getMock();

        $listener->expects(static::once())
            ->method('__invoke');

        $dispatcher->addListener(EntityWrittenContainerEvent::class, $listener);

        $operations = [
            new SyncOperation('manufacturers', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m3'), 'name' => 'third manufacturer'],
                ['id' => $ids->create('m4'), 'name' => 'fourth manufacturer'],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('taxes', 'tax', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('t1')],
                ['id' => $ids->get('t2')],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('countries', 'country', SyncOperation::ACTION_DELETE, [
                ['id' => $ids->get('c1')],
                ['id' => $ids->get('c2')],
            ], PlatformRequest::API_VERSION),
        ];

        $this->service->sync($operations, Context::createDefaultContext(), $behavior);

        $exists = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['m1', 'm2', 'm3', 'm4']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(4, $exists);

        $exists = $this->connection->fetchAll(
            'SELECT id FROM tax WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['t1', 't2']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);

        $exists = $this->connection->fetchAll(
            'SELECT id FROM country WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids->getList(['c1', 'c2']))],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);
    }

    public function testSingleOperationParameter(): void
    {
        $ids = new TestDataCollection();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this
            ->getMockBuilder(CallableClass::class)
            ->getMock();

        $listener->expects(static::once())
            ->method('__invoke');

        $dispatcher->addListener(EntityWrittenContainerEvent::class, $listener);

        $operations = [
            new SyncOperation('write', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('m1'), 'name' => 'first manufacturer'],
                ['id' => $ids->create('m2'), 'name' => 'second manufacturer'],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('write', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('t1'), 'name' => 'first tax', 'taxRate' => 10],
                ['id' => $ids->create('t2'), 'name' => 'second tax', 'taxRate' => 10],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('write', 'country', SyncOperation::ACTION_UPSERT, [
                ['id' => $ids->create('c1'), 'name' => 'first country'],
                ['id' => $ids->create('c2'), 'name' => 'second country'],
            ], PlatformRequest::API_VERSION),
        ];

        $behavior = new SyncBehavior(false, true);

        $this->service->sync($operations, Context::createDefaultContext(), $behavior);
    }

    public function testError(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operation = new SyncOperation(
            'write',
            'product_manufacturer',
            SyncOperation::ACTION_UPSERT,
            [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2],
            ],
            PlatformRequest::API_VERSION
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

        static::assertFalse($result->isSuccess());
        $operation = $result->get('write');

        static::assertInstanceOf(SyncOperationResult::class, $operation);
        static::assertTrue($operation->hasError());

        static::assertTrue($operation->has(0));
        static::assertTrue($operation->has(1));

        $written = $operation->get(0);
        static::assertEquals([], $written['errors']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);

        $written = $operation->get(1);
        static::assertIsArray($written['errors']);
        static::assertCount(1, $written['errors']);
        static::assertArrayHasKey('entities', $written);
        static::assertEmpty($written['entities']);
    }

    public function testFailOnErrorContinues(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operation = new SyncOperation(
            'write',
            'product_manufacturer',
            SyncOperation::ACTION_UPSERT,
            [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2],
            ],
            PlatformRequest::API_VERSION
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(1, $written);
    }

    public function testFailOnErrorRollback(): void
    {
        $this->connection->rollBack();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operation = new SyncOperation(
            'write',
            'product_manufacturer',
            SyncOperation::ACTION_UPSERT,
            [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2],
            ],
            PlatformRequest::API_VERSION
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(true));

        $this->connection->beginTransaction();

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(0, $written);

        $operation = $result->get('write');

        $written = $operation->get(0);
        static::assertEmpty($written['entities']);
        static::assertEmpty($written['errors']);

        $written = $operation->get(1);
        static::assertEmpty($written['entities']);
        static::assertNotEmpty($written['errors']);
    }

    public function testFailOnErrorWithMultipleOperations(): void
    {
        $this->connection->rollBack();

        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $operations = [
            new SyncOperation('write', 'product_manufacturer', SyncOperation::ACTION_UPSERT, [
                ['id' => $id1, 'name' => 'first manufacturer'],
                ['id' => $id2],
            ], PlatformRequest::API_VERSION),
            new SyncOperation('write2', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $id1, 'name' => 'first tax'],
            ], PlatformRequest::API_VERSION),
        ];

        $result = $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior(true));

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(0, $written);

        $written = $this->connection->fetchAll(
            'SELECT id FROM tax WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(0, $written);

        $operation = $result->get('write');
        $step = $operation->get(0);
        static::assertEmpty($step['entities']);
        static::assertEmpty($step['errors']);

        $step = $operation->get(1);
        static::assertEmpty($step['entities']);
        static::assertNotEmpty($step['errors']);

        $operation = $result->get('write2');
        $step = $operation->get(0);
        static::assertEmpty($step['entities']);
        static::assertNotEmpty($step['errors']);

        $this->connection->beginTransaction();
    }

    public function testWriteDeprecatedFieldLeadsToError(): void
    {
        $operations = [
            new SyncOperation('write', 'deprecated', SyncOperation::ACTION_UPSERT, [
                ['id' => Uuid::randomHex(), 'price' => 10],
            ], 2),
        ];

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $repoMock = $this->createMock(EntityRepositoryInterface::class);
        $repoMock->expects(static::once())
            ->method('getDefinition')
            ->willReturn($deprecatedDefinition);
        $repoMock->expects(static::never())
            ->method('upsert');

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects(static::once())
            ->method('getRepository')
            ->with($deprecatedDefinition->getEntityName())
            ->willReturn($repoMock);

        $defaultConverter = $this->createMock(DefaultApiConverter::class);
        $defaultConverter->method('isDeprecated')->willReturn(false);
        $defaultConverter->method('convert')->willReturnArgument(2);

        $apiVersionConverter = new ApiVersionConverter(
            new ConverterRegistry(
                [
                    new DeprecatedConverter(),
                ],
                $defaultConverter
            )
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $apiVersionConverter,
            $this->getContainer()->get(EntityWriter::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true));

        static::assertFalse($result->isSuccess());

        $results = $result->get('write')->getResult();
        static::assertCount(1, $results);

        $errors = $results[0]['errors'];
        static::assertCount(1, $errors);

        $error = $errors[0];
        static::assertEquals('FRAMEWORK__WRITE_REMOVED_FIELD', $error['code']);
        static::assertEquals('/0/price', $error['source']['pointer']);
    }

    public function testWriteDeprecatedEntityLeadsToError(): void
    {
        $operations = [
            new SyncOperation('write', 'deprecated_entity', SyncOperation::ACTION_UPSERT, [
                ['id' => Uuid::randomHex(), 'price' => 10],
            ], 2),
        ];

        $deprecatedEntityDefinition = new DeprecatedEntityDefinition();
        $deprecatedEntityDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $repositoryMock = $this->createMock(EntityRepositoryInterface::class);
        $repositoryMock->expects(static::any())
            ->method('getDefinition')
            ->willReturn($deprecatedEntityDefinition);

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects(static::once())
            ->method('getRepository')
            ->with($deprecatedEntityDefinition->getEntityName())
            ->willReturn($repositoryMock);

        $defaultConverter = $this->createMock(DefaultApiConverter::class);
        $defaultConverter->method('isDeprecated')->willReturn(false);
        $defaultConverter->method('convert')->willReturnArgument(2);

        $apiVersionConverter = new ApiVersionConverter(
            new ConverterRegistry(
                [
                    new DeprecatedConverter(),
                ],
                $defaultConverter
            )
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $apiVersionConverter,
            $this->getContainer()->get(EntityWriter::class),
            $this->getContainer()->get('event_dispatcher')
        );
        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true));

        static::assertFalse($result->isSuccess());

        $results = $result->get('write')->getResult();
        static::assertCount(1, $results);

        $errors = $results[0]['errors'];
        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertEquals('/0', $error['source']['pointer']);
        static::assertEquals('You entity deprecated_entity is not available or deprecated in api version 2.', $error['detail']);
    }

    public function testDeprecatedPayloadIsConverted(): void
    {
        $id = Uuid::randomHex();

        $operations = [
            new SyncOperation('write', 'deprecated', SyncOperation::ACTION_UPSERT, [
                ['id' => $id, 'price' => 10],
            ], 1),
        ];

        $deprecatedDefinition = new DeprecatedDefinition();
        $deprecatedDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $repoMock = $this->createMock(EntityRepositoryInterface::class);
        $repoMock->expects(static::once())
            ->method('upsert')
            ->with(
                [
                    ['id' => $id, 'prices' => [10]],
                ],
                static::isInstanceOf(Context::class)
            )
            ->willReturn($this->dummyEntityWrittenEvent($id));

        $repoMock->expects(static::once())
            ->method('getDefinition')
            ->willReturn($deprecatedDefinition);

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects(static::once())
            ->method('getRepository')
            ->with($deprecatedDefinition->getEntityName())
            ->willReturn($repoMock);

        $defaultConverter = $this->createMock(DefaultApiConverter::class);
        $defaultConverter->method('isDeprecated')->willReturn(false);
        $defaultConverter->method('convert')->willReturnArgument(2);

        $versionConverter = new ApiVersionConverter(
            new ConverterRegistry(
                [
                    new DeprecatedConverter(),
                ],
                $defaultConverter
            )
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $versionConverter,
            $this->getContainer()->get(EntityWriter::class),
            $this->getContainer()->get('event_dispatcher')
        );
        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true));

        static::assertTrue($result->isSuccess(), print_r($result, true));
    }

    private function dummyEntityWrittenEvent(string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    'deprecated',
                    [
                        new EntityWriteResult($id, [], 'deprecated', EntityWriteResult::OPERATION_INSERT),
                    ],
                    Context::createDefaultContext()
                ),
            ]),
            []
        );
    }
}
