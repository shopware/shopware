<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

class FlowPayloadUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?EntityRepositoryInterface $flowRepository;

    private ?Connection $connection;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testCreate(): void
    {
        $this->createTestData();

        $flow = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), $this->ids->context)->first();

        $trueCaseNextAction = new ActionSequence();
        $trueCaseNextAction->action = AddOrderTagAction::getName();
        $trueCaseNextAction->config = [
            'tagId' => $this->ids->get('tag_id2'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];
        $trueCaseNextAction->flowId = $this->ids->get('flow_id');
        $trueCaseNextAction->sequenceId = $this->ids->get('flow_sequence_id2');

        $trueCase = new ActionSequence();
        $trueCase->action = AddOrderTagAction::getName();
        $trueCase->config = [
            'tagId' => $this->ids->get('tag_id'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];
        $trueCase->nextAction = $trueCaseNextAction;
        $trueCase->flowId = $this->ids->get('flow_id');
        $trueCase->sequenceId = $this->ids->get('flow_sequence_id1');

        $sequence = new IfSequence();
        $sequence->ruleId = $this->ids->get('rule_id');
        $sequence->trueCase = $trueCase;
        $sequence->flowId = $this->ids->get('flow_id');
        $sequence->sequenceId = $this->ids->get('flow_sequence_id');

        $expected = [$sequence];

        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), $expected)), $flow->getPayload());
    }

    public function testUpdate(): void
    {
        $this->createTestData();

        $this->flowRepository->update([
            [
                'id' => $this->ids->get('flow_id'),
                'sequences' => [
                    [
                        'id' => $this->ids->create('flow_sequence_id1'),
                        'actionName' => RemoveOrderTagAction::getName(),
                    ],
                    [
                        'id' => $this->ids->create('flow_sequence_id2'),
                        'trueCase' => false,
                        'position' => 1,
                    ],
                ],
            ],
        ], $this->ids->context);

        $flow = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), $this->ids->context)->first();

        $falseCase = new ActionSequence();
        $falseCase->action = AddOrderTagAction::getName();
        $falseCase->config = [
            'tagId' => $this->ids->get('tag_id2'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];
        $falseCase->flowId = $this->ids->get('flow_id');
        $falseCase->sequenceId = $this->ids->get('flow_sequence_id2');

        $trueCase = new ActionSequence();
        $trueCase->action = RemoveOrderTagAction::getName();
        $trueCase->config = [
            'tagId' => $this->ids->get('tag_id'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];
        $trueCase->flowId = $this->ids->get('flow_id');
        $trueCase->sequenceId = $this->ids->get('flow_sequence_id1');

        $sequence = new IfSequence();
        $sequence->ruleId = $this->ids->get('rule_id');
        $sequence->trueCase = $trueCase;
        $sequence->falseCase = $falseCase;
        $sequence->flowId = $this->ids->get('flow_id');
        $sequence->sequenceId = $this->ids->get('flow_sequence_id');

        $expected = [$sequence];

        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), $expected)), $flow->getPayload());
    }

    public function testPayloadShouldUpdateAfterDeletedAllSequence(): void
    {
        $this->createTestData();

        $flowSequenceRepository = $this->getContainer()->get('flow_sequence.repository');
        $flowSequenceRepository->delete([
            ['id' => $this->ids->get('flow_sequence_id2')],
            ['id' => $this->ids->get('flow_sequence_id1')],
            ['id' => $this->ids->get('flow_sequence_id')],
        ], $this->ids->context);

        $criteria = new Criteria([$this->ids->get('flow_id')]);
        $criteria->addAssociation('sequences');
        $flow = $this->flowRepository->search($criteria, $this->ids->context)->first();

        static::assertSame([], $flow->getSequences()->getElements());
        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), [])), $flow->getPayload());
    }

    public function testPayloadShouldBeCorrectWithoutSequence(): void
    {
        $this->flowRepository->create([[
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => true,
        ]], $this->ids->context);

        $criteria = new Criteria([$this->ids->get('flow_id')]);
        $criteria->addAssociation('sequences');
        $flow = $this->flowRepository->search($criteria, $this->ids->context)->first();

        static::assertSame([], $flow->getSequences()->getElements());
        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), [])), $flow->getPayload());
    }

    private function createTestData(): void
    {
        $this->flowRepository->create([[
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'payload' => null,
            'invalid' => true,
            'sequences' => [
                [
                    'id' => $this->ids->create('flow_sequence_id'),
                    'parentId' => null,
                    'ruleId' => $this->ids->create('rule_id'),
                    'actionName' => null,
                    'config' => [],
                    'position' => 1,
                    'rule' => [
                        'id' => $this->ids->get('rule_id'),
                        'name' => 'Test rule',
                        'priority' => 1,
                        'conditions' => [
                            ['type' => (new AlwaysValidRule())->getName()],
                        ],
                    ],
                ],
                [
                    'id' => $this->ids->create('flow_sequence_id1'),
                    'parentId' => $this->ids->get('flow_sequence_id'),
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
                [
                    'id' => $this->ids->create('flow_sequence_id2'),
                    'parentId' => $this->ids->get('flow_sequence_id'),
                    'ruleId' => null,
                    'actionName' => AddOrderTagAction::getName(),
                    'config' => [
                        'tagId' => $this->ids->get('tag_id2'),
                        'entity' => OrderDefinition::ENTITY_NAME,
                    ],
                    'position' => 2,
                    'trueCase' => true,
                ],
            ],
        ]], $this->ids->context);
    }
}
