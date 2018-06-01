<?php declare(strict_types=1);

namespace Shopware\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Content\Product\ProductDefinition;
use Shopware\Defaults;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\Field\BoolField;
use Shopware\Framework\ORM\Field\FloatField;
use Shopware\Framework\ORM\Field\JsonArrayField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Version\Definition\VersionCommitDataDefinition;
use Shopware\Framework\ORM\Write\EntityWriter;
use Shopware\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Framework\ORM\Write\FieldException\InvalidFieldException;
use Shopware\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\WriteContext;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class JsonArrayFieldTest extends KernelTestCase
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
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
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
        $this->assertCount(2, $ex->getExceptions());

        $fieldException = $ex->getExceptions()[0];
        $this->assertEquals(InvalidFieldException::class, get_class($fieldException));
        $this->assertEquals('/price/gross', $fieldException->getPath());

        $fieldException = $ex->getExceptions()[1];
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

    public function testFieldShouldOnlyContainDefinedProperties(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 13.2, 'ignore' => 'me'],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'rate' => 15],
            'categories' => [
                ['id' => $id->getHex(), 'name' => 'asd'],
            ],
        ];

        $this->getWriter()->insert(ProductDefinition::class, [$data], $context);

        $price = $this->connection->fetchColumn('SELECT price FROM product WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertNotEmpty($price);

        $price = json_decode($price, true);

        $this->assertEquals(
            ['gross' => 15, 'net' => 13.2],
            $price
        );
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
        $context = $this->createWriteContext();

        $data = [
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

    /**
     * @return WriteContext
     */
    protected function createWriteContext(): WriteContext
    {
        $context = WriteContext::createFromApplicationContext(ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

        return $context;
    }

    private function getWriter(): EntityWriterInterface
    {
        return self::$container->get(EntityWriter::class);
    }
}

class NestedDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'product';
    }

    public static function getFields(): FieldCollection
    {
        return new FieldCollection([
            new JsonArrayField('data', 'data', [
                (new FloatField('gross', 'gross'))->setFlags(new Required()),
                new FloatField('net', 'net'),
                new JsonArrayField('foo', 'foo', [
                    new StringField('bar', 'bar'),
                    new JsonArrayField('baz', 'baz', [
                        new BoolField('deep', 'deep'),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function getRepositoryClass(): string
    {
        return '';
    }

    public static function getBasicCollectionClass(): string
    {
        return '';
    }

    public static function getBasicStructClass(): string
    {
        return '';
    }

    public static function getWrittenEventClass(): string
    {
        return '';
    }

    public static function getDeletedEventClass(): string
    {
        return '';
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return '';
    }
}
