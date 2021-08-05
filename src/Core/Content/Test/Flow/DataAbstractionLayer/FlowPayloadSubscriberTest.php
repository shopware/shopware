<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\Flow\Indexing\FlowPayloadSubscriber;
use Shopware\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class FlowPayloadSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?Connection $connection;

    private FlowPayloadSubscriber $flowPayloadSubscriber;

    private Context $context;

    /**
     * @var FlowPayloadUpdater|MockObject
     */
    private $updater;

    private ?FlowDefinition $flowDefinition;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_8225', $this);

        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->updater = $this->createMock(FlowPayloadUpdater::class);

        $this->flowPayloadSubscriber = new FlowPayloadSubscriber(
            $this->updater,
            $this->getContainer()->get(CacheClearer::class)
        );

        $this->flowDefinition = $this->getContainer()->get(FlowDefinition::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testLoadValidFlowWithoutPayload(): void
    {
        $flow = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => false,
            '_uniqueIdentifier' => $this->ids->get('flow_id'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $loadedEvent = new EntityLoadedEvent($this->flowDefinition, [$flow], $this->context);

        static::assertNull($flow->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$this->ids->get('flow_id')])
            ->willReturn([$this->ids->get('flow_id') => ['payload' => serialize(new Flow($this->ids->get('flow_id'), [])), 'invalid' => false]]);

        $this->flowPayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($flow->getPayload());
        static::assertInstanceOf(Flow::class, $flow->getPayload());
        static::assertFalse($flow->isInvalid());
    }

    public function testLoadInvalidFlowWithoutPayload(): void
    {
        $flow = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => true,
            '_uniqueIdentifier' => $this->ids->get('flow_id'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $loadedEvent = new EntityLoadedEvent($this->flowDefinition, [$flow], $this->context);

        static::assertNull($flow->getPayload());

        $this->updater
            ->expects(static::never())
            ->method('update');

        $this->flowPayloadSubscriber->unserialize($loadedEvent);

        static::assertNull($flow->getPayload());
        static::assertTrue($flow->isInvalid());
    }

    public function testLoadValidRuleWithPayload(): void
    {
        $flow = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => new Flow($this->ids->get('flow_id'), []),
            'invalid' => false,
            '_uniqueIdentifier' => $this->ids->get('flow_id'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $loadedEvent = new EntityLoadedEvent($this->flowDefinition, [$flow], $this->context);

        static::assertNotNull($flow->getPayload());

        $this->updater
            ->expects(static::never())
            ->method('update');

        $this->flowPayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($flow->getPayload());
        static::assertFalse($flow->isInvalid());
    }

    public function testLoadValidFlowsWithoutPayload(): void
    {
        $flow = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => false,
            '_uniqueIdentifier' => $this->ids->get('flow_id'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id1'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $flow2 = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id2'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => false,
            '_uniqueIdentifier' => $this->ids->get('flow_id2'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id2'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id2'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $loadedEvent = new EntityLoadedEvent($this->flowDefinition, [$flow, $flow2], $this->context);

        static::assertNull($flow->getPayload());
        static::assertNull($flow2->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$this->ids->get('flow_id'), $this->ids->get('flow_id2')])
                ->willReturn(
                    [
                        $this->ids->get('flow_id') => ['payload' => serialize(new Flow($this->ids->get('flow_id'), [])), 'invalid' => false],
                        $this->ids->get('flow_id2') => ['payload' => serialize(new Flow($this->ids->get('flow_id'), [])), 'invalid' => false],
                    ]
                );
        $this->flowPayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($flow->getPayload());
        static::assertInstanceOf(Flow::class, $flow->getPayload());
        static::assertFalse($flow->isInvalid());
        static::assertNotNull($flow2->getPayload());
        static::assertInstanceOf(Flow::class, $flow2->getPayload());
        static::assertFalse($flow2->isInvalid());
    }

    public function testLoadValidAndInvalidFlowsWithoutPayload(): void
    {
        $flow = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => false,
            '_uniqueIdentifier' => $this->ids->get('flow_id'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id1'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $flow2 = (new FlowEntity())->assign([
            'id' => $this->ids->create('flow_id2'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => true,
            '_uniqueIdentifier' => $this->ids->get('flow_id2'),
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id2'),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id2'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => false,
                ],
            ],
        ]);
        $loadedEvent = new EntityLoadedEvent($this->flowDefinition, [$flow, $flow2], $this->context);

        static::assertNull($flow->getPayload());
        static::assertNull($flow2->getPayload());

        $this->updater
            ->expects(static::once())
            ->method('update')
            ->with([$this->ids->create('flow_id')])
            ->willReturn(
                [$this->ids->create('flow_id') => ['payload' => serialize(new Flow($this->ids->get('flow_id'), [])), 'invalid' => false]]
            );

        $this->flowPayloadSubscriber->unserialize($loadedEvent);

        static::assertNotNull($flow->getPayload());
        static::assertInstanceOf(Flow::class, $flow->getPayload());
        static::assertFalse($flow->isInvalid());
        static::assertNull($flow2->getPayload());
        static::assertTrue($flow2->isInvalid());
    }

    public function testLoadValidFlowsFromDatabase(): void
    {
        $id = Uuid::randomHex();
        $this->connection->createQueryBuilder()
            ->insert('flow')
            ->values(['id' => ':id', 'name' => ':name', 'event_name' => ':eventName', 'active' => '1', 'priority' => 3, 'invalid' => '0', 'created_at' => ':createdAt'])
            ->setParameter('name', 'Flow')
            ->setParameter('eventName', CheckoutOrderPlacedEvent::EVENT_NAME)
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->execute();

        $this->connection->createQueryBuilder()
            ->insert('flow_sequence')
            ->values(['id' => ':id', 'flow_id' => ':flowId', 'parent_id' => 'null', 'rule_id' => 'null', 'action_name' => ':actionName', 'created_at' => ':createdAt'])
            ->setParameter('id', Uuid::randomBytes())
            ->setParameter('flowId', Uuid::fromHexToBytes($id))
            ->setParameter('actionName', AddOrderTagAction::getName())
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->execute();

        /** @var FlowEntity $flow */
        $flow = $this->getContainer()->get('flow.repository')->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($flow);
        static::assertNotNull($flow->getPayload());
        static::assertInstanceOf(Flow::class, $flow->getPayload());
        static::assertFalse($flow->isInvalid());

        $flowData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('flow')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->execute()
            ->fetchAssociative();

        static::assertNotNull($flowData['payload']);
        static::assertSame(0, (int) $flowData['invalid']);
    }

    public function testLoadInvalidFlowsFromDatabase(): void
    {
        $id = Uuid::randomHex();
        $this->connection->createQueryBuilder()
            ->insert('flow')
            ->values(['id' => ':id', 'name' => ':name', 'event_name' => ':eventName', 'active' => '1', 'priority' => 3, 'invalid' => '1', 'created_at' => ':createdAt'])
            ->setParameter('name', 'Flow')
            ->setParameter('eventName', CheckoutOrderPlacedEvent::EVENT_NAME)
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->execute();

        $this->connection->createQueryBuilder()
            ->insert('flow_sequence')
            ->values(['id' => ':id', 'flow_id' => ':flowId', 'parent_id' => 'null', 'rule_id' => 'null', 'action_name' => ':actionName', 'created_at' => ':createdAt'])
            ->setParameter('id', Uuid::randomBytes())
            ->setParameter('flowId', Uuid::fromHexToBytes($id))
            ->setParameter('actionName', AddOrderTagAction::getName())
            ->setParameter('createdAt', (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->execute();

        /** @var FlowEntity $flow */
        $flow = $this->getContainer()->get('flow.repository')->search(new Criteria([$id]), $this->context)->get($id);
        static::assertNotNull($flow);
        static::assertNull($flow->getPayload());
        static::assertTrue($flow->isInvalid());

        $flowData = $this->connection->createQueryBuilder()
            ->select(['payload', 'invalid'])
            ->from('flow')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($id))
            ->execute()
            ->fetchAssociative();

        static::assertNull($flowData['payload']);
        static::assertSame(1, (int) $flowData['invalid']);
    }
}
