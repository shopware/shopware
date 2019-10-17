<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Converter\ConverterRegistry;
use Shopware\Core\Framework\Api\Converter\ConverterService;
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
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedConverter;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedDefinition;
use Shopware\Core\Framework\Test\Api\Converter\fixtures\DeprecatedEntityDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
            ]
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false, PlatformRequest::API_VERSION));

        static::assertTrue($result->isSuccess());
        $operation = $result->get('write');

        static::assertInstanceOf(SyncOperationResult::class, $operation);
        static::assertFalse($operation->hasError());
        static::assertTrue($operation->isSuccess());

        static::assertTrue($operation->has(0));
        static::assertTrue($operation->has(1));

        $written = $operation->get(0);
        static::assertNull($written['error']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);

        $written = $operation->get(1);
        static::assertNull($written['error']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);
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
            ]
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false, PlatformRequest::API_VERSION));

        static::assertFalse($result->isSuccess());
        $operation = $result->get('write');

        static::assertInstanceOf(SyncOperationResult::class, $operation);
        static::assertTrue($operation->hasError());
        static::assertFalse($operation->isSuccess());

        static::assertTrue($operation->has(0));
        static::assertTrue($operation->has(1));

        $written = $operation->get(0);
        static::assertNull($written['error']);
        static::assertArrayHasKey('entities', $written);
        static::assertArrayHasKey('product_manufacturer', $written['entities']);
        static::assertArrayHasKey('product_manufacturer_translation', $written['entities']);

        $written = $operation->get(1);
        static::assertNotNull($written['error']);
        static::assertIsString($written['error']);
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
            ]
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false, PlatformRequest::API_VERSION));

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
            ]
        );

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(true, PlatformRequest::API_VERSION));

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(0, $written);

        $this->connection->beginTransaction();
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
            ]),
            new SyncOperation('write2', 'tax', SyncOperation::ACTION_UPSERT, [
                ['id' => $id1, 'name' => 'first tax'],
            ]),
        ];

        $result = $this->service->sync($operations, Context::createDefaultContext(), new SyncBehavior(true, PlatformRequest::API_VERSION));

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
        $this->connection->beginTransaction();
    }

    public function testWriteDeprecatedFieldLeadsToError(): void
    {
        $this->connection->rollBack();

        $operations = [
            new SyncOperation('write', 'deprecated', SyncOperation::ACTION_UPSERT, [
                ['id' => Uuid::randomHex(), 'price' => 10],
            ]),
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

        $converterService = new ConverterService(
            new ConverterRegistry([
                new DeprecatedConverter(),
            ])
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $converterService
        );

        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true, 2));

        static::assertFalse($result->isSuccess());

        $results = $result->get('write')->getResult();
        static::assertCount(1, $results);

        $errors = $results[0]['error'];
        static::assertCount(1, $errors);
        static::assertEquals('FRAMEWORK__WRITE_REMOVED_FIELD', $errors[0]['code']);
        static::assertEquals('/price', $errors[0]['source']['pointer']);

        $this->connection->beginTransaction();
    }

    public function testWriteDeprecatedEntityLeadsToError(): void
    {
        $this->connection->rollBack();

        $operations = [
            new SyncOperation('write', 'deprecated_entity', SyncOperation::ACTION_UPSERT, [
                ['id' => Uuid::randomHex(), 'price' => 10],
            ]),
        ];

        $deprecatedEntityDefinition = new DeprecatedEntityDefinition();
        $deprecatedEntityDefinition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $definitionRegistry->expects(static::once())
            ->method('getRepository')
            ->with($deprecatedEntityDefinition->getEntityName())
            ->willReturn($this->createMock(EntityRepositoryInterface::class));

        $converterService = new ConverterService(
            new ConverterRegistry([
                new DeprecatedConverter(),
            ])
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $converterService
        );
        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true, 2));

        static::assertFalse($result->isSuccess());

        $results = $result->get('write')->getResult();
        static::assertCount(1, $results);

        $error = $results[0]['error'];
        static::assertEquals('Writing of entity: "deprecated_entity" is not allowed in v2 of the api.', $error);

        $this->connection->beginTransaction();
    }

    public function testDeprecatedPayloadIsConverted(): void
    {
        $this->connection->rollBack();
        $id = Uuid::randomHex();

        $operations = [
            new SyncOperation('write', 'deprecated', SyncOperation::ACTION_UPSERT, [
                ['id' => $id, 'price' => 10],
            ]),
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

        $converterService = new ConverterService(
            new ConverterRegistry([
                new DeprecatedConverter(),
            ])
        );

        $syncService = new SyncService(
            $definitionRegistry,
            $this->getContainer()->get(Connection::class),
            $converterService
        );
        $result = $syncService->sync($operations, Context::createDefaultContext(), new SyncBehavior(true, 1));

        static::assertTrue($result->isSuccess(), print_r($result, true));

        $this->connection->beginTransaction();
    }

    private function dummyEntityWrittenEvent(string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    'deprecated',
                    [
                        new EntityWriteResult($id, [], 'deprecated', null),
                    ],
                    Context::createDefaultContext()
                ),
            ]),
            []
        );
    }
}
