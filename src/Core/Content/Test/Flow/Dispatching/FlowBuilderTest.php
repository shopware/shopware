<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\FlowBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class FlowBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private FlowBuilder $flowBuilder;

    protected function setUp(): void
    {
        $this->flowBuilder = $this->getContainer()->get(FlowBuilder::class);
    }

    public function testBuildOnlyAction(): void
    {
        $flowId = Uuid::randomHex();
        $flowSequences = [
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => null,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => null,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.remove.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
        ];

        $flow = $this->flowBuilder->build($flowId, $flowSequences);
        static::assertIsArray($flow->getSequences());
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->action);
        static::assertEquals('action.remove.order.tag', $flow->getSequences()[1]->action);
    }

    public function testBuildWithActionBeforeAction(): void
    {
        $flowId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $flowSequences = [
            [
                'flow_id' => $flowId,
                'sequence_id' => $parentId,
                'parent_id' => null,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.delay',
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $parentId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $parentId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
        ];

        $flow = $this->flowBuilder->build($flowId, $flowSequences);
        static::assertIsArray($flow->getSequences());
        static::assertEquals('action.delay', $flow->getSequences()[0]->action);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->nextAction->action);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->nextAction->nextAction->action);
    }

    public function testBuildWithActionBeforeIf(): void
    {
        $flowId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $fatherId = Uuid::randomHex();
        $flowSequences = [
            [
                'flow_id' => $flowId,
                'sequence_id' => $parentId,
                'parent_id' => null,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.delay',
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => $fatherId,
                'parent_id' => $parentId,
                'app_flow_action_id' => null,
                'rule_id' => Uuid::randomHex(),
                'display_group' => '1',
                'position' => '1',
                'action_name' => null,
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $fatherId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '1',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $fatherId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
        ];

        $flow = $this->flowBuilder->build($flowId, $flowSequences);
        static::assertIsArray($flow->getSequences());
        static::assertEquals('action.delay', $flow->getSequences()[0]->action);
        static::assertNotNull($flow->getSequences()[0]->nextAction->ruleId);
        static::assertNotNull($flow->getSequences()[0]->nextAction->trueCase);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->nextAction->trueCase->action);
        static::assertNotNull($flow->getSequences()[0]->nextAction->falseCase);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->nextAction->falseCase->action);
    }

    public function testBuildWithIfBeforeAction(): void
    {
        $flowId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $flowSequences = [
            [
                'flow_id' => $flowId,
                'sequence_id' => $parentId,
                'parent_id' => null,
                'app_flow_action_id' => null,
                'rule_id' => Uuid::randomHex(),
                'display_group' => '1',
                'position' => '1',
                'action_name' => null,
                'config' => '',
                'true_case' => '0',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $parentId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '1',
            ],
            [
                'flow_id' => $flowId,
                'sequence_id' => Uuid::randomHex(),
                'parent_id' => $parentId,
                'app_flow_action_id' => null,
                'rule_id' => '',
                'display_group' => '1',
                'position' => '1',
                'action_name' => 'action.add.order.tag',
                'config' => '',
                'true_case' => '0',
            ],
        ];

        $flow = $this->flowBuilder->build($flowId, $flowSequences);
        static::assertIsArray($flow->getSequences());
        static::assertNotNull($flow->getSequences()[0]->ruleId);
        static::assertNotNull($flow->getSequences()[0]->trueCase);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->trueCase->action);
        static::assertNotNull($flow->getSequences()[0]->falseCase);
        static::assertEquals('action.add.order.tag', $flow->getSequences()[0]->falseCase->action);
    }
}
