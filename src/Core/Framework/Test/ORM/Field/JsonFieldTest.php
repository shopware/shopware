<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Version\Definition\VersionCommitDataDefinition;
use Shopware\Core\Framework\ORM\Write\EntityWriter;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\UnexpectedFieldException;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\NestedDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonFieldTest extends KernelTestCase
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

    public function testNullableJsonField(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => null,
        ];

        $this->getWriter()->insert(JsonDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `_test_nullable`');

        $this->assertCount(1, $data);
        $this->assertEquals($id->getBytes(), $data[0]['id']);
        $this->assertNull($data[0]['data']);
    }

    public function testMissingProperty(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        $this->assertInstanceOf(WriteStackException::class, $ex);
        $this->assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/net', $fieldException->getPath());
    }

    public function testMultipleMissingProperties(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['foo' => 'bar'],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        $this->assertInstanceOf(WriteStackException::class, $ex);
        $this->assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(UnexpectedFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/foo', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/gross', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[2];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/net', $fieldException->getPath());
    }

    public function testPropertyTypes(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 'strings are not allowed'],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        $this->assertInstanceOf(WriteStackException::class, $ex);
        $this->assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/net', $fieldException->getPath());
    }

    public function testUnexpectedFieldShouldThrowException(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 13.2, 'fail' => 'me'],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(ProductDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        $this->assertInstanceOf(WriteStackException::class, $ex);
        $this->assertCount(1, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(UnexpectedFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/fail', $fieldException->getPath());
    }

    public function testWithoutMappingShouldAcceptAnyKey(): void
    {
        $id = Uuid::uuid4();
        $dt = new \DateTime();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'commit' => ['id' => $id->getHex(), 'versionId' => $id->getHex()],
            'entityName' => 'foobar',
            'entityId' => ['id' => $id->getHex(), 'foo' => 'bar'],
            'action' => 'create',
            'payload' => json_encode(['foo' => 'bar']),
            'createdAt' => $dt,
        ];

        $this->getWriter()->insert(VersionCommitDataDefinition::class, [$data], $context);

        $entityId = $this->connection->fetchColumn('SELECT entity_id FROM version_commit_data WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($entityId);

        $entityId = json_decode($entityId, true);

        $this->assertEquals(
            $data['entityId'],
            $entityId
        );
    }

    public function testFieldNesting(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'data' => [
                'net' => 15,
                'foo' => [
                    'bar' => false,
                    'baz' => [
                        'deep' => 'invalid',
                    ],
                ],
            ],
        ];

        $ex = null;
        try {
            $this->getWriter()->insert(NestedDefinition::class, [$data], $context);
        } catch (WriteStackException $ex) {
        }

        $this->assertInstanceOf(WriteStackException::class, $ex);
        $this->assertCount(3, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/data/gross', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/data/foo/bar', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[2];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/data/foo/baz/deep', $fieldException->getPath());
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
