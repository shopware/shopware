<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class RulePayloadSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private RulePayloadSubscriber $rulePayloadSubscriber;

    private Context $context;

    private MockObject&RulePayloadUpdater $updater;

    private RuleDefinition $ruleDefinition;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->updater = $this->createMock(RulePayloadUpdater::class);

        $this->rulePayloadSubscriber = new RulePayloadSubscriber(
            $this->updater,
            $this->getContainer()->get(ScriptTraces::class),
            $this->getContainer()->getParameter('kernel.cache_dir'),
            $this->getContainer()->getParameter('kernel.debug')
        );

        $this->ruleDefinition = $this->getContainer()->get(RuleDefinition::class);
    }

    public function testLoadValidRuleWithoutPayload(): void
    {
        $id = Uuid::randomHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $loadedEvent = new EntityLoadedEvent($this->ruleDefinition, [$rule], $this->context);

        static::assertNull($rule->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$id])
            ->willReturn([$id => ['payload' => serialize(new AndRule()), 'invalid' => false]]);

        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(Rule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());
    }

    public function testLoadInvalidRuleWithoutPayload(): void
    {
        $id = Uuid::randomHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => true, '_uniqueIdentifier' => $id]);
        $loadedEvent = new EntityLoadedEvent($this->ruleDefinition, [$rule], $this->context);

        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());

        $this->updater
            ->expects(static::never())
            ->method('update');

        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());
    }

    public function testLoadValidRuleWithPayload(): void
    {
        $id = Uuid::randomHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => serialize(new AndRule()), 'invalid' => false, '_uniqueIdentifier' => $id]);
        $loadedEvent = new EntityLoadedEvent($this->ruleDefinition, [$rule], $this->context);

        static::assertNotNull($rule->getPayload());

        $this->updater
            ->expects(static::never())
            ->method('update');
        $this->rulePayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($rule->getPayload());
        static::assertFalse($rule->isInvalid());
    }

    public function testLoadValidRulesWithoutPayload(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $rule2 = (new RuleEntity())->assign(['id' => $id2, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id2]);
        $loadedEvent = new EntityLoadedEvent($this->ruleDefinition, [$rule, $rule2], $this->context);

        static::assertNull($rule->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$id, $id2])
                ->willReturn(
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
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $rule = (new RuleEntity())->assign(['id' => $id, 'payload' => null, 'invalid' => false, '_uniqueIdentifier' => $id]);
        $rule2 = (new RuleEntity())->assign(['id' => $id2, 'payload' => null, 'invalid' => true, '_uniqueIdentifier' => $id2]);
        $loadedEvent = new EntityLoadedEvent($this->ruleDefinition, [$rule, $rule2], $this->context);

        static::assertNull($rule->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$id])
            ->willReturn(
                [$id => ['payload' => serialize(new AndRule()), 'invalid' => false]]
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
        $id = Uuid::randomHex();
        $this->connection->createQueryBuilder()
            ->insert('rule')
            ->values(['id' => ':id', 'name' => ':name', 'priority' => 3, 'invalid' => '0', 'created_at' => ':createdAt'])
            ->setParameter('name', 'Rule')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();

        $this->connection->createQueryBuilder()
            ->insert('rule_condition')
            ->values(['id' => ':id', 'type' => ':type', 'value' => 'null', 'position' => '0', 'rule_id' => ':ruleId', 'created_at' => ':createdAt'])
            ->setParameter('id', Uuid::randomBytes())
            ->setParameter('type', (new AndRule())->getName())
            ->setParameter('ruleId', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();

        $rule = $this->getContainer()->get('rule.repository')->search(new Criteria([$id]), $this->context)->get($id);
        static::assertInstanceOf(RuleEntity::class, $rule);
        static::assertNotNull($rule->getPayload());
        static::assertInstanceOf(AndRule::class, $rule->getPayload());
        static::assertFalse($rule->isInvalid());

        $ruleData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('rule')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->executeQuery()
            ->fetchAssociative();

        static::assertIsArray($ruleData);
        static::assertNotNull($ruleData['payload']);
        static::assertSame(0, (int) $ruleData['invalid']);
    }

    public function testLoadInvalidRulesFromDatabase(): void
    {
        $id = Uuid::randomHex();
        $this->connection->createQueryBuilder()
            ->insert('rule')
            ->values(['id' => ':id', 'name' => ':name', 'priority' => 3, 'invalid' => '0', 'created_at' => ':createdAt'])
            ->setParameter('name', 'Rule')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();

        $this->connection->createQueryBuilder()
            ->insert('rule_condition')
            ->values(['id' => ':id', 'type' => ':type', 'value' => 'null', 'position' => '0', 'rule_id' => ':ruleId', 'created_at' => ':createdAt'])
            ->setParameter('id', Uuid::randomBytes())
            ->setParameter('type', 'invalid')
            ->setParameter('ruleId', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();

        $rule = $this->getContainer()->get('rule.repository')->search(new Criteria([$id]), $this->context)->get($id);
        static::assertInstanceOf(RuleEntity::class, $rule);
        static::assertNotNull($rule);
        static::assertNull($rule->getPayload());
        static::assertTrue($rule->isInvalid());

        $ruleData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('rule')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->executeQuery()
            ->fetchAssociative();

        static::assertIsArray($ruleData);
        static::assertNull($ruleData['payload']);
        static::assertSame(1, (int) $ruleData['invalid']);
    }
}
