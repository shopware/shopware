<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping\DataAbstractionLayer\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodRules\ShippingMethodRuleDefinition;
use Shopware\Core\Checkout\Shipping\DataAbstractionLayer\Indexing\ShippingMethodIndexer;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ShippingMethodIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ShippingMethodIndexer
     */
    private $indexer;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->indexer = $this->getContainer()->get(ShippingMethodIndexer::class);
        $this->context = Context::createDefaultContext();
    }

    public function testIndexIndexesAllAssociations(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $id = $this->createShippingMethod();
            $countAssociations = rand(1, 10);
            for ($j = 0; $j < $countAssociations; ++$j) {
                $this->addShippingMethodRule($id);
            }
        }

        $this->indexer->index(new \DateTime());

        $entities = $this->connection->fetchAll(
            'SELECT id, availability_rule_ids, COUNT(rule_id) AS countAssociations 
             FROM shipping_method 
             INNER JOIN shipping_method_rule smr ON shipping_method.id = smr.shipping_method_id 
             GROUP BY id, availability_rule_ids'
        );

        static::assertGreaterThan(0, count($entities));
        foreach ($entities as $entity) {
            static::assertCount((int) $entity['countAssociations'], json_decode($entity['availability_rule_ids']));
        }
    }

    public function testIndexIndexesWithoutAssociations(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->createShippingMethod();
        }

        $this->indexer->index(new \DateTime());

        $entities = $this->connection->fetchAll(
            'SELECT id, availability_rule_ids, COUNT(rule_id) AS countAssociations 
             FROM shipping_method 
             LEFT OUTER JOIN shipping_method_rule smr ON shipping_method.id = smr.shipping_method_id
             WHERE id <> :id
             GROUP BY id, availability_rule_ids',
            ['id' => Uuid::fromHexToBytes(Defaults::SHIPPING_METHOD)]
        );

        static::assertGreaterThan(0, count($entities));
        foreach ($entities as $entity) {
            static::assertNotNull($entity['availability_rule_ids']);
            static::assertEmpty(json_decode($entity['availability_rule_ids']));
        }
    }

    public function testRefreshIndexesWithoutAssociation(): void
    {
        $shippingMethodId = Uuid::fromBytesToHex($this->createShippingMethod());

        $writeResult = new EntityWriteResult(
            $shippingMethodId,
            ['name' => 'Test'],
            new EntityExistence(ShippingMethodDefinition::class, ['id' => $shippingMethodId], true, false, false, [])
        );
        $nestedEvents = new NestedEventCollection(
            [new EntityWrittenEvent(ShippingMethodDefinition::class, [$writeResult], $this->context)]
        );
        $event = new EntityWrittenContainerEvent($this->context, $nestedEvents, []);
        $this->indexer->refresh($event);

        $entity = $this->connection->fetchAssoc(
            'SELECT id, availability_rule_ids, COUNT(rule_id) AS countAssociations 
             FROM shipping_method 
             LEFT OUTER JOIN shipping_method_rule smr ON shipping_method.id = smr.shipping_method_id 
             WHERE id = :id GROUP BY id, availability_rule_ids',
            ['id' => Uuid::fromHexToBytes($shippingMethodId)]
        );

        static::assertNotNull($entity['availability_rule_ids']);
        static::assertEmpty(json_decode($entity['availability_rule_ids']));
    }

    public function testRefreshIndexesWithAssociation(): void
    {
        $shippingMethodId = $this->createShippingMethod();
        $this->addShippingMethodRule($shippingMethodId);

        $writeResult = new EntityWriteResult(
            Uuid::fromBytesToHex($shippingMethodId),
            ['name' => 'Test'],
            new EntityExistence(ShippingMethodDefinition::class, ['id' => Uuid::fromBytesToHex($shippingMethodId)], true, false, false, [])
        );
        $nestedEvents = new NestedEventCollection(
            [new EntityWrittenEvent(ShippingMethodDefinition::class, [$writeResult], $this->context)]
        );
        $event = new EntityWrittenContainerEvent($this->context, $nestedEvents, []);
        $this->indexer->refresh($event);

        $entity = $this->connection->fetchAssoc(
            'SELECT id, availability_rule_ids, count(rule_id) as countAssociations 
             from shipping_method 
             left outer join shipping_method_rule smr on shipping_method.id = smr.shipping_method_id 
             where id = :id group by id, availability_rule_ids',
            ['id' => $shippingMethodId]
        );

        static::assertNotNull($entity['availability_rule_ids']);
        static::assertCount((int) $entity['countAssociations'], json_decode($entity['availability_rule_ids']));
    }

    public function testRefreshIndexesWithAssociationWrittenEvent(): void
    {
        $shippingMethodId = $this->createShippingMethod();
        $shippingMethodIdHex = Uuid::fromBytesToHex($shippingMethodId);
        $ruleId = $this->addShippingMethodRule($shippingMethodId);
        $ruleIdHex = Uuid::fromBytesToHex($ruleId);

        $writeResult = new EntityWriteResult(
            ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex],
            ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex],
            new EntityExistence(
                ShippingMethodRuleDefinition::class,
                ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex], true, false, false, []
            )
        );
        $nestedEvents = new NestedEventCollection(
            [new EntityWrittenEvent(ShippingMethodRuleDefinition::class, [$writeResult], $this->context)]
        );
        $event = new EntityWrittenContainerEvent($this->context, $nestedEvents, []);
        $this->indexer->refresh($event);

        $entity = $this->connection->fetchAssoc(
            'SELECT id, availability_rule_ids, count(rule_id) as countAssociations 
             from shipping_method 
             left outer join shipping_method_rule smr on shipping_method.id = smr.shipping_method_id 
             where id = :id group by id, availability_rule_ids',
            ['id' => $shippingMethodId]
        );

        static::assertNotNull($entity['availability_rule_ids']);
        static::assertCount((int) $entity['countAssociations'], json_decode($entity['availability_rule_ids']));
    }

    public function testRefreshIndexesDeletedToEmptyArray(): void
    {
        $shippingMethodId = $this->createShippingMethod();
        $shippingMethodIdHex = Uuid::fromBytesToHex($shippingMethodId);
        $ruleId = $this->addShippingMethodRule($shippingMethodId);
        $ruleIdHex = Uuid::fromBytesToHex($ruleId);

        $this->connection->delete('shipping_method_rule', ['shipping_method_id' => $shippingMethodId, 'rule_id' => $ruleId]);

        $writeResult = new EntityWriteResult(
            ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex],
            ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex],
            new EntityExistence(
                ShippingMethodRuleDefinition::class,
                ['ruleId' => $ruleIdHex, 'shippingMethodId' => $shippingMethodIdHex], true, false, false, []
            )
        );
        $nestedEvents = new NestedEventCollection(
            [new EntityDeletedEvent(ShippingMethodRuleDefinition::class, [$writeResult], $this->context)]
        );
        $event = new EntityWrittenContainerEvent($this->context, $nestedEvents, []);
        $this->indexer->refresh($event);

        $entity = $this->connection->fetchAssoc(
            'SELECT id, availability_rule_ids, COUNT(rule_id) AS countAssociations 
             FROM shipping_method 
             LEFT OUTER JOIN shipping_method_rule smr ON shipping_method.id = smr.shipping_method_id 
             WHERE id = :id GROUP BY id, availability_rule_ids',
            ['id' => $shippingMethodId]
        );

        static::assertNotNull($entity['availability_rule_ids']);
        static::assertCount((int) $entity['countAssociations'], json_decode($entity['availability_rule_ids']));
    }

    private function createShippingMethod(): string
    {
        $id = Uuid::randomBytes();
        $this->connection->insert(
            'shipping_method', [
                'id' => $id,
                'active' => 1,
                'bind_shippingfree' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $id;
    }

    private function addShippingMethodRule(string $shippingMethodId): string
    {
        $ruleId = Uuid::randomBytes();
        $this->connection->insert(
            'rule',
            ['id' => $ruleId, 'name' => 'Test', 'priority' => 0, 'invalid' => 0, 'created_at' => date('Y-m-d H:i:s')]
        );

        $this->connection->insert(
            'shipping_method_rule',
            ['shipping_method_id' => $shippingMethodId, 'rule_id' => $ruleId, 'created_at' => date('Y-m-d H:i:s')]
        );

        return $ruleId;
    }
}
