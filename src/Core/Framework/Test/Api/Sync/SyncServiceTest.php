<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Sync;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncOperationResult;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

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

    public function testSingleOperation()
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

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

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

    public function testError()
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

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

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

    public function testFailOnErrorContinues()
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

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(false));

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(1, $written);
    }

    public function testFailOnErrorRollback()
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

        $result = $this->service->sync([$operation], Context::createDefaultContext(), new SyncBehavior(true));

        static::assertFalse($result->isSuccess());

        $written = $this->connection->fetchAll(
            'SELECT id FROM product_manufacturer WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id1, $id2])],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(0, $written);

        $this->connection->beginTransaction();
    }

    public function testFailOnErrorWithMultipleOperations()
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
        $this->connection->beginTransaction();
    }
}
