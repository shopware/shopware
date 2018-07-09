<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Field;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Logging\EchoSQLLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\Search\PaginationCriteria;
use Shopware\Core\Framework\ORM\VersionManager;
use Shopware\Core\Framework\ORM\Write\EntityWriter;
use Shopware\Core\Framework\ORM\Write\EntityWriterInterface;
use Shopware\Core\Framework\ORM\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ORM\Field\TestDefinition\BlacklistRuleDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BlacklistRuleFieldTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $ruleRepository;

    /**
     * @var EntityRepository
     */
    private $repo;

    public function setUp()
    {
        self::bootKernel();
        $this->connection = self::$container->get(Connection::class);

        $this->ruleRepository = self::$container->get('rule.repository');
        $this->repo = new EntityRepository(
            BlacklistRuleDefinition::class,
            self::$container->get(EntityReaderInterface::class),
            self::$container->get(VersionManager::class),
            self::$container->get(EntitySearcherInterface::class),
            self::$container->get(EntityAggregatorInterface::class),
            self::$container->get('event_dispatcher')
        );

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS test_nullable;
CREATE TABLE `test_nullable` (
  `id` varbinary(16) NOT NULL,
  `blacklisted_rule_ids` longtext NULL,
  `test_nullable_id` binary(16) NULL,
  `test_nullable_tenant_id` binary(16) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeUpdate($nullableTable);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testNullableRuleIds(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $data = [
            'id' => $id->getHex(),
            'blacklistedRuleIds' => null,
        ];

        $this->getWriter()->insert(BlacklistRuleDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `test_nullable`');

        $this->assertCount(1, $data);
        $this->assertEquals($id->getBytes(), $data[0]['id']);
        $this->assertNull($data[0]['blacklisted_rule_ids']);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([]));
        $this->assertCount(1, $entities);
    }

    public function testSingleRuleId(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $rule1 = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'blacklistedRuleIds' => [$rule1->getHex()],
        ];

        $this->getWriter()->insert(BlacklistRuleDefinition::class, [$data], $context);

        $data = $this->connection->fetchAssoc('SELECT * FROM `test_nullable`');
        $this->assertEquals($id->getBytes(), $data['id']);
        $this->assertNotEmpty($data['blacklisted_rule_ids']);
        $ids = json_decode($data['blacklisted_rule_ids']);
        $this->assertSame([$rule1->getHex()], $ids);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([]));
        $this->assertCount(1, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$rule1->getHex()]));
        $this->assertCount(0, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$id->getHex()]));
        $this->assertCount(1, $entities);
    }

    public function testMultipleRuleId(): void
    {
        $id = Uuid::uuid4();
        $context = $this->createWriteContext();

        $rule1 = Uuid::uuid4();
        $rule2 = Uuid::uuid4();

        $data = [
            'id' => $id->getHex(),
            'blacklistedRuleIds' => [$rule1->getHex(), $rule2->getHex()],
        ];

        $this->getWriter()->insert(BlacklistRuleDefinition::class, [$data], $context);

        $data = $this->connection->fetchAssoc('SELECT * FROM `test_nullable`');
        $this->assertEquals($id->getBytes(), $data['id']);
        $this->assertNotEmpty($data['blacklisted_rule_ids']);
        $ids = json_decode($data['blacklisted_rule_ids']);
        $this->assertSame([$rule1->getHex(), $rule2->getHex()], $ids);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([]));
        $this->assertCount(1, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$id->getHex()]));
        $this->assertCount(1, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$rule1->getHex()]));
        $this->assertCount(0, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$rule2->getHex()]));
        $this->assertCount(0, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$rule1->getHex(), $rule2->getHex()]));
        $this->assertCount(0, $entities);

        $entities = $this->repo->read(new ReadCriteria([$id->getHex()]), $this->getContext([$rule1->getHex(), $id->getHex()]));
        $this->assertCount(0, $entities);
    }

    public function testOneToMany()
    {
        $id = Uuid::uuid4()->getHex();
        $child1 = Uuid::uuid4()->getHex();
        $child2 = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'oneToMany' => [
                ['id' => $child1, 'testNullableId' => $id, 'blacklistedRuleIds' => [$rule1, $rule3]],
                ['id' => $child2, 'testNullableId' => $id, 'blacklistedRuleIds' => [$rule2, $rule3]]
            ]
        ];

        $context = $this->createWriteContext();
        $this->getWriter()->insert(BlacklistRuleDefinition::class, [$data], $context);

        $data = $this->connection->fetchAll('SELECT * FROM `test_nullable`');
        $this->assertCount(3, $data);

        $criteria = new ReadCriteria([$id]);
        $criteria->addAssociation('oneToMany', new PaginationCriteria(10));

        $entities = $this->repo->read($criteria, $this->getContext([]));
        $this->assertCount(1, $entities);
        $this->assertCount(2, $entities->get($id)->get('oneToMany'));

        $entities = $this->repo->read($criteria, $this->getContext([$rule1]));
        $this->assertCount(1, $entities);
        $this->assertCount(1, $entities->get($id)->get('oneToMany'));
        $this->assertTrue($entities->get($id)->get('oneToMany')->has($child2));
        $this->assertFalse($entities->get($id)->get('oneToMany')->has($child1));

        $entities = $this->repo->read($criteria, $this->getContext([$rule2]));
        $this->assertCount(1, $entities);
        $this->assertCount(1, $entities->get($id)->get('oneToMany'));
        $this->assertFalse($entities->get($id)->get('oneToMany')->has($child2));
        $this->assertTrue($entities->get($id)->get('oneToMany')->has($child1));

        $entities = $this->repo->read($criteria, $this->getContext([$id]));
        $this->assertCount(1, $entities);
        $this->assertCount(2, $entities->get($id)->get('oneToMany'));
        $this->assertTrue($entities->get($id)->get('oneToMany')->has($child2));
        $this->assertTrue($entities->get($id)->get('oneToMany')->has($child1));

        $entities = $this->repo->read($criteria, $this->getContext([$rule1, $rule2]));
        $this->assertCount(1, $entities);
        $this->assertCount(0, $entities->get($id)->get('oneToMany'));

        $entities = $this->repo->read($criteria, $this->getContext([$rule3]));
        $this->assertCount(1, $entities);
        $this->assertCount(0, $entities->get($id)->get('oneToMany'));




    }

    protected function getContext(array $rules)
    {
        return new Context(
            Defaults::TENANT_ID,
            Defaults::TOUCHPOINT,
            null,
            $rules,
            Defaults::CURRENCY,
            Defaults::LANGUAGE
        );
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
