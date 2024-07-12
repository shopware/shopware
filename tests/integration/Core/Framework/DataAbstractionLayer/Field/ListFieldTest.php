<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
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
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

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
        $this->tearDownDefinitions();
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
        static::assertSame(Uuid::fromHexToBytes($id), $data[0]['id']);
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
        static::assertSame(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertSame('[]', $data[0]['data']);
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
        static::assertSame(Uuid::fromHexToBytes($id), $data[0]['id']);
        static::assertSame('["foo","bar","loo"]', $data[0]['data']);
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

        $fieldExceptionOne = $ex->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionOne);
        static::assertSame('/0/data', $fieldExceptionOne->getPath());
        static::assertSame('/0', $fieldExceptionOne->getViolations()->get(0)->getPropertyPath());

        $fieldExceptionTwo = $ex->getExceptions()[1];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionTwo);
        static::assertSame('/0/data', $fieldExceptionTwo->getPath());
        static::assertSame('/1', $fieldExceptionTwo->getViolations()->get(0)->getPropertyPath());

        $fieldExceptionThree = $ex->getExceptions()[2];
        static::assertInstanceOf(WriteConstraintViolationException::class, $fieldExceptionThree);
        static::assertSame('/0/data', $fieldExceptionThree->getPath());
        static::assertSame('/3', $fieldExceptionThree->getViolations()->get(0)->getPropertyPath());
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
        static::assertSame('😄', $payload['screenshotUrls'][0]);
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    private function getWriter(): EntityWriterInterface
    {
        return $this->getContainer()->get(EntityWriter::class);
    }
}
