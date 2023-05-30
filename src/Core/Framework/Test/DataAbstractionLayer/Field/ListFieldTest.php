<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ListDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;

/**
 * @internal
 */
class ListFieldTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `data` longtext CHARACTER SET utf8mb4,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`id`)
)  DEFAULT CHARSET=utf8mb4;
EOF;
        $this->connection->executeStatement($nullableTable);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `_test_nullable`');

        parent::tearDown();
    }

    public function testNullableListField(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => null,
        ];

        $this->getWriter()->insert($this->registerDefinition(ListDefinition::class), [$data], $context);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertNull($data[0]['data']);
    }

    public function testEmptyList(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => [],
        ];

        $this->getWriter()->insert($this->registerDefinition(ListDefinition::class), [$data], $context);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertEquals('[]', $data[0]['data']);
    }

    public function testWithData(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => ['foo', 'bar', 'loo'],
        ];

        $this->getWriter()->insert($this->registerDefinition(ListDefinition::class), [$data], $context);

        $data = $this->connection->fetchAllAssociative('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertEquals('["foo","bar","loo"]', $data[0]['data']);
    }

    public function testListType(): void
    {
        $id = Uuid::randomHex();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id,
            'data' => [false, 10, 'string', 10.123],
        ];

        $ex = null;

        try {
            $this->getWriter()->insert($this->registerDefinition(ListDefinition::class), [$data], $context);
        } catch (WriteException $ex) {
        }

        static::assertInstanceOf(WriteException::class, $ex);
        static::assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(WriteConstraintViolationException::class, $fieldException::class);
        static::assertEquals('/0/data', $fieldException->getPath());
        static::assertEquals('/0', $fieldException->getViolations()->get(0)->getPropertyPath());

        $fieldException = $ex->getExceptions()[1];
        static::assertEquals(WriteConstraintViolationException::class, $fieldException::class);
        static::assertEquals('/0/data', $fieldException->getPath());
        static::assertEquals('/1', $fieldException->getViolations()->get(0)->getPropertyPath());

        $fieldException = $ex->getExceptions()[2];
        static::assertEquals(WriteConstraintViolationException::class, $fieldException::class);
        static::assertEquals('/0/data', $fieldException->getPath());
        static::assertEquals('/3', $fieldException->getViolations()->get(0)->getPropertyPath());
    }

    public function testWriteUtf8(): void
    {
        $type = [
            'name' => 'test',
            'screenshotUrls' => ['😄'],
        ];

        $written = $this->getWriter()->insert($this->registerDefinition(SalesChannelTypeDefinition::class), [$type], $this->createWriteContext());

        static::assertArrayHasKey(SalesChannelTypeDefinition::ENTITY_NAME, $written);
        static::assertCount(1, $written[SalesChannelTypeDefinition::ENTITY_NAME]);
        $payload = $written[SalesChannelTypeDefinition::ENTITY_NAME][0]->getPayload();
        static::assertCount(1, $payload['screenshotUrls']);
        static::assertEquals('😄', $payload['screenshotUrls'][0]);
    }

    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        return $context;
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }
}
