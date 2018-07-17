<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\VersionManager;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\DateTimeDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CreateAtAndUpdatedAtFieldTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $repo;

    public function setUp()
    {
        self::bootKernel();
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
        $this->repo = new EntityRepository(
            DateTimeDefinition::class,
            self::$container->get(EntityReaderInterface::class),
            self::$container->get(VersionManager::class),
            self::$container->get(EntitySearcherInterface::class),
            self::$container->get(EntityAggregatorInterface::class),
            self::$container->get('event_dispatcher')
        );

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS `date_time_test`;
CREATE TABLE IF NOT EXISTS `date_time_test` (
  `id` varbinary(16) NOT NULL,
  `name` varchar(500) NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
    }

    public function tearDown(): void
    {
        $this->connection->executeUpdate('DROP TABLE `date_time_test`');
        $this->connection->rollBack();

        parent::tearDown();
    }

    public function testCreatedAtDefinedAutomatically(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
        ];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        static::assertTrue($entities->has($id));

        /** @var ArrayStruct $entity */
        $entity = $entities->get($id);

        static::assertNotNull($entity->get('createdAt'));
        static::assertInstanceOf(\DateTime::class, $entity->get('createdAt'));
        static::assertNull($entity->get('updatedAt'));
    }

    public function testICanProvideCreatedAt()
    {
        $id = Uuid::uuid4()->getHex();

        $date = new \DateTime('2000-01-01 12:12:12.990');

        $data = [
            'id' => $id,
            'createdAt' => $date,
        ];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        static::assertTrue($entities->has($id));

        /** @var ArrayStruct $entity */
        $entity = $entities->get($id);

        static::assertNotNull($entity->get('createdAt'));
        static::assertInstanceOf(\DateTime::class, $entity->get('createdAt'));

        static::assertEquals(
            $date->format('Y-m-d H:i:s.v'),
            $entity->get('createdAt')->format('Y-m-d H:i:s.v')
        );
    }

    public function testCreatedAtWithNullWillBeOverwritten()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'createdAt' => null,
        ];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        static::assertTrue($entities->has($id));

        /** @var ArrayStruct $entity */
        $entity = $entities->get($id);

        static::assertNotNull($entity->get('createdAt'));
        static::assertInstanceOf(\DateTime::class, $entity->get('createdAt'));
    }

    public function testUpdatedAtWillBeSetAutomatically(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = ['id' => $id];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNull($entity->get('updatedAt'));

        $data = ['id' => $id, 'name' => 'updated'];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->update([$data], $context);
        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNotNull($entity->get('updatedAt'));
    }

    public function testUpdatedAtWithNullWorks()
    {
        $id = Uuid::uuid4()->getHex();

        $data = ['id' => $id];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNull($entity->get('updatedAt'));

        $data = ['id' => $id, 'name' => 'updated', 'updatedAt' => null];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->update([$data], $context);
        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNotNull($entity->get('updatedAt'));
    }

    public function testUpdatedAtCanNotBeDefined()
    {
        $id = Uuid::uuid4()->getHex();

        $date = new \DateTime('2012-01-01');

        $data = ['id' => $id, 'updatedAt' => $date];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->create([$data], $context);

        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNull($entity->get('updatedAt'));

        $data = ['id' => $id, 'name' => 'updated', 'updatedAt' => $date];

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->repo->update([$data], $context);
        $entities = $this->repo->read(new ReadCriteria([$id]), $context);

        /** @var ArrayStruct $entity */
        static::assertTrue($entities->has($id));
        $entity = $entities->get($id);
        static::assertNotNull($entity->get('updatedAt'));

        static::assertNotEquals(
            $date->format('Y-m-d'),
            $entity->get('updatedAt')->format('Y-m-d')
        );
    }
}
