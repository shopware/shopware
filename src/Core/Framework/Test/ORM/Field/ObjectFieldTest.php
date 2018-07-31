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
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\ObjectDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ObjectFieldTest extends KernelTestCase
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

    public function testNullableObjectField(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => null,
        ];

        $this->getWriter()->insert(ObjectDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertNull($data[0]['data']);
    }

    public function testWithNonStructClass(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => new \stdClass(),
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ObjectDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        static::assertInstanceOf(WriteStackException::class, $ex);
        static::assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        static::assertEquals(InvalidFieldException::class, get_class($fieldException));
        static::assertEquals('/data', $fieldException->getPath());

        $messages = $fieldException->toArray();
        static::assertEquals('The object must be of type "\Shopware\Core\Framework\Struct\Struct" to be persisted in a ObjectField.', $messages[0]['message']);
    }

    public function testWithStruct(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $struct = new PriceStruct(10.0, 20.0);

        $data = [
            'id' => $id->getHex(),
            'data' => $struct,
        ];

        $this->getWriter()->insert(ObjectDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        static::assertCount(1, $data);
        static::assertEquals($id->getBytes(), $data[0]['id']);
        static::assertEquals('{"_class":"Shopware\\\\Core\\\\Framework\\\\Pricing\\\\PriceStruct","net":10,"gross":20,"extensions":[]}', $data[0]['data']);
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
