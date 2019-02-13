<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class RulePayloadSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RulePayloadSubscriber
     */
    private $rulePayloadSubscriber;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RulePayloadIndexer
     */
    private $indexer;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->indexer = $this->createMock(RulePayloadIndexer::class);
        $this->rulePayloadSubscriber = new RulePayloadSubscriber($this->indexer);
    }

    public function testLoadValidRuleWithoutPayload(): void
    {
        $collection = new RuleCollection();
        $id = Uuid::uuid4()->getHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $collection->add($rule);
        $loadedEvent = new EntityLoadedEvent(RuleDefinition::class, $collection, $this->context);

        static::assertNull($rule->getPayload());

        $this->indexer->expects($this->once())->method('update')->with([$id => $id])->willReturn([$id => ['payload' => serialize(new AndRule()), 'invalid' => false]]);
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(Rule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());
    }

    public function testLoadInvalidRuleWithoutPayload(): void
    {
        $collection = new RuleCollection();
        $id = Uuid::uuid4()->getHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => true, '_uniqueIdentifier' => $id]);
        $collection->add($rule);
        $loadedEvent = new EntityLoadedEvent(RuleDefinition::class, $collection, $this->context);

        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());

        $this->indexer->expects($this->never())->method('update');
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());
    }

    public function testLoadValidRuleWithPayload(): void
    {
        $collection = new RuleCollection();
        $id = Uuid::uuid4()->getHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => serialize(new AndRule()), 'invalid' => false, '_uniqueIdentifier' => $id]);
        $collection->add($rule);
        $loadedEvent = new EntityLoadedEvent(RuleDefinition::class, $collection, $this->context);

        static::assertNotNull($rule->getPayload());

        $this->indexer->expects($this->never())->method('update');
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertFalse($rule->isInvalid());
    }

    public function testLoadValidRulesWithoutPayload(): void
    {
        $collection = new RuleCollection();
        $id = Uuid::uuid4()->getHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $collection->add($rule);
        $id2 = Uuid::uuid4()->getHex();
        $rule2 = (new RuleEntity())->assign(['id' => $id2, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id2]);
        $collection->add($rule2);
        $loadedEvent = new EntityLoadedEvent(RuleDefinition::class, $collection, $this->context);

        static::assertNull($rule->getPayload());

        $this->indexer->expects($this->once())->method('update')->with([$id => $id, $id2 => $id2])->willReturn(
            [
                $id => ['payload' => serialize(new AndRule()), 'invalid' => false],
                $id2 => ['payload' => serialize(new OrRule()), 'invalid' => false],
            ]
        );
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());
        static::assertNotNull($rule2->getPayload());
        static::assertInstanceOf(OrRule::class, $rule2->getPayload());
        static::assertFalse($rule2->isInvalid());
    }

    public function testLoadValidAndInvalidRulesWithoutPayload(): void
    {
        $collection = new RuleCollection();
        $id = Uuid::uuid4()->getHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $collection->add($rule);
        $id2 = Uuid::uuid4()->getHex();
        $rule2 = (new RuleEntity())->assign(['id' => $id2, 'payload' => null, 'invalid' => true, '_uniqueIdentifier' => $id2]);
        $collection->add($rule2);
        $loadedEvent = new EntityLoadedEvent(RuleDefinition::class, $collection, $this->context);

        static::assertNull($rule->getPayload());

        $this->indexer->expects($this->once())->method('update')->with([$id => $id])->willReturn(
            [
                $id => ['payload' => serialize(new AndRule()), 'invalid' => false],
            ]
        );
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());
        static::assertNull($rule2->getPayload());
        static::assertTrue($rule2->isInvalid());
    }

    public function testLoadValidRulesFromDatabase(): void
    {
        $id = Uuid::uuid4();
        $this->connection->createQueryBuilder()
            ->insert('rule')
            ->values(['id' => ':id', 'name' => ':name', 'priority' => 3, 'invalid' => '0', 'created_at' => 'NOW()'])
            ->setParameter('name', 'Rule')
            ->setParameter('id', $id->getBytes())
            ->execute();

        $this->connection->createQueryBuilder()
            ->insert('rule_condition')
            ->values(['id' => ':id', 'type' => ':type', 'value' => 'null', 'position' => '0', 'rule_id' => ':ruleId'])
            ->setParameter('id', Uuid::uuid4()->getBytes())
            ->setParameter('type', (new AndRule())->getName())
            ->setParameter('ruleId', $id->getBytes())
            ->execute();

        /** @var RuleEntity $rule */
        $rule = $this->getContainer()->get('rule.repository')->search(new Criteria([$id->getHex()]), $this->context)->get($id->getHex());
        static::assertNotNull($rule);
        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());

        $ruleData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('rule')
            ->where('id = :id')
            ->setParameter('id', $id->getBytes())
            ->execute()
            ->fetch();

        static::assertNotNull($ruleData['payload']);
        static::assertEquals(0, $ruleData['invalid']);
    }

    public function testLoadInvalidRulesFromDatabase(): void
    {
        $id = Uuid::uuid4();
        $this->connection->createQueryBuilder()
            ->insert('rule')
            ->values(['id' => ':id', 'name' => ':name', 'priority' => 3, 'invalid' => '0', 'created_at' => 'NOW()'])
            ->setParameter('name', 'Rule')
            ->setParameter('id', $id->getBytes())
            ->execute();

        $this->connection->createQueryBuilder()
            ->insert('rule_condition')
            ->values(['id' => ':id', 'type' => ':type', 'value' => 'null', 'position' => '0', 'rule_id' => ':ruleId'])
            ->setParameter('id', Uuid::uuid4()->getBytes())
            ->setParameter('type', 'invalid')
            ->setParameter('ruleId', $id->getBytes())
            ->execute();

        /** @var RuleEntity $rule */
        $rule = $this->getContainer()->get('rule.repository')->search(new Criteria([$id->getHex()]), $this->context)->get($id->getHex());
        static::assertNotNull($rule);
        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());

        $ruleData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('rule')
            ->where('id = :id')
            ->setParameter('id', $id->getBytes())
            ->execute()
            ->fetch();

        static::assertNull($ruleData['payload']);
        static::assertEquals(1, $ruleData['invalid']);
    }
}
