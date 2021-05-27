<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Action\FlowAction;
use Shopware\Core\Content\Flow\SequenceTree\Sequence;
use Shopware\Core\Content\Flow\SequenceTree\SequenceTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class FlowPayloadUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?EntityRepositoryInterface $flowRepository;

    private ?Connection $connection;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_8225', $this);

        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());
    }

    public function testCreate(): void
    {
        $this->createTestData();

        $flow = $this->flowRepository->search(new Criteria([$this->ids->get('flow_id')]), $this->ids->context)->first();

        $trueCaseNextAction = new Sequence();
        $trueCaseNextAction->action = FlowAction::ADD_TAG;
        $trueCaseNextAction->config = [
            'tagId' => $this->ids->get('tag_id2'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];

        $trueCase = new Sequence();
        $trueCase->action = FlowAction::ADD_TAG;
        $trueCase->config = [
            'tagId' => $this->ids->get('tag_id'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];
        $trueCase->nextAction = $trueCaseNextAction;

        $sequence = new Sequence();
        $sequence->ruleId = $this->ids->get('rule_id');
        $sequence->trueCase = $trueCase;

        $expected = [$sequence];

        static::assertSame(serialize(new SequenceTree($expected)), $flow->getPayload());
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
                        'actionName' => FlowAction::REMOVE_TAG,
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

        $falseCase = new Sequence();
        $falseCase->action = FlowAction::ADD_TAG;
        $falseCase->config = [
            'tagId' => $this->ids->get('tag_id2'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];

        $trueCase = new Sequence();
        $trueCase->action = FlowAction::REMOVE_TAG;
        $trueCase->config = [
            'tagId' => $this->ids->get('tag_id'),
            'entity' => OrderDefinition::ENTITY_NAME,
        ];

        $sequence = new Sequence();
        $sequence->ruleId = $this->ids->get('rule_id');
        $sequence->trueCase = $trueCase;
        $sequence->falseCase = $falseCase;

        $expected = [$sequence];

        static::assertSame(serialize(new SequenceTree($expected)), $flow->getPayload());
    }

    private function createTestData(): void
    {
        $this->flowRepository->create([[
            'id' => $this->ids->create('flow_id'),
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
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
                    'actionName' => FlowAction::ADD_TAG,
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
                    'actionName' => FlowAction::ADD_TAG,
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
