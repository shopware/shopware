<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Write\EntityWriter;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\ListDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ListFieldTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    public function setUp()
    {
        self::bootKernel();
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();

        $nullableTable = <<<EOF
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `data` longtext NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
    }

    public function tearDown(): void
    {
        $this->connection->executeUpdate('DROP TABLE `_test_nullable`');

        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testNullableListField(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => null,
        ];

        $this->getWriter()->insert(ListDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertNull($data[0]['data']);
    }

    public function testEmptyList(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => [],
        ];

        $this->getWriter()->insert(ListDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEquals('[]', $data[0]['data']);
    }

    public function testWithData(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => ['foo', 'bar', 'loo'],
        ];

        $this->getWriter()->insert(ListDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEquals('["foo","bar","loo"]', $data[0]['data']);
    }

    public function testListType(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => [false, 10, 'string', 10.123],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ListDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InvalidFieldException::class, get_class($fieldException));
        static::assertEquals('/data/0', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
        static::assertEquals(InvalidFieldException::class, get_class($fieldException));
        static::assertEquals('/data/1', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[2];
        static::assertEquals(InvalidFieldException::class, get_class($fieldException));
        static::assertEquals('/data/3', $fieldException->getPath());
    }

    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext(Defaults::TENANT_ID));

        return $context;
    }

    private function getWriter(): EntityWriterInterface
    {
        return self::$container->get(EntityWriter::class);
    }
}
