<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopware\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Dispatching\Struct\IfSequence;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @internal
 */
#[Package('business-ops')]
class FlowPayloadUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $flowRepository;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->ids = new TestDataCollection();
    }

    public function testCreate(): void
    {
        $this->createTestData();

        $flow = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), Context::createDefaultContext())->first();
        static::assertNotNull($flow);

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

        $flat = [];
        $flat[$this->ids->create('flow_sequence_id1')] = $trueCase;
        $flat[$this->ids->get('flow_sequence_id')] = $sequence;

        $expected = [$sequence];

        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), $expected, $flat)), $flow->getPayload());
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
        ], Context::createDefaultContext());

        $flow = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), Context::createDefaultContext())->first();

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

        $flat = [];
        $flat[$this->ids->create('flow_sequence_id1')] = $trueCase;
        $flat[$this->ids->create('flow_sequence_id2')] = $falseCase;
        $flat[$this->ids->get('flow_sequence_id')] = $sequence;

        $expected = [$sequence];

        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), $expected, $flat)), $flow->getPayload());
    }

    public function testPayloadShouldUpdateAfterDeletedAllSequence(): void
    {
        $this->createTestData();

        $flowSequenceRepository = $this->getContainer()->get('flow_sequence.repository');
        $flowSequenceRepository->delete([
            ['id' => $this->ids->get('flow_sequence_id2')],
            ['id' => $this->ids->get('flow_sequence_id1')],
            ['id' => $this->ids->get('flow_sequence_id')],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('flow_id')]);
        $criteria->addAssociation('sequences');
        $flow = $this->flowRepository->search($criteria, Context::createDefaultContext())->first();

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
        ]], Context::createDefaultContext());

        $criteria = new Criteria([$this->ids->get('flow_id')]);
        $criteria->addAssociation('sequences');
        $flow = $this->flowRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertSame([], $flow->getSequences()->getElements());
        static::assertSame(serialize(new Flow($this->ids->get('flow_id'), [])), $flow->getPayload());
    }

    public function testJumpFlow(): void
    {
        $this->createTestData();

        /** @var FlowEntity $flowEntity */
        $flowEntity = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), Context::createDefaultContext())->first();

        /** @var string $payload */
        $payload = $flowEntity->getPayload();
        $flow = unserialize($payload);
        static::assertTrue($flow->getSequences()[0] instanceof IfSequence);

        $flat = $flow->getFlat();
        static::assertTrue($flat[$this->ids->create('flow_sequence_id1')] instanceof ActionSequence);
        static::assertTrue($flat[$this->ids->create('flow_sequence_id')] instanceof IfSequence);

        $flow->jump($this->ids->create('flow_sequence_id1'));
        static::assertTrue($flow->getSequences()[0] instanceof ActionSequence);
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
        ]], Context::createDefaultContext());
    }
}
