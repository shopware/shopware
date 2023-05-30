<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('business-ops')]
class Sequence extends Struct
{
    public string $flowId;

    public string $sequenceId;

    public static function createIF(
        string $ruleId,
        string $flowId,
        string $sequenceId,
        ?Sequence $true,
        ?Sequence $false
    ): IfSequence {
        $sequence = new IfSequence();
        $sequence->ruleId = $ruleId;
        $sequence->trueCase = $true;
        $sequence->falseCase = $false;
        $sequence->flowId = $flowId;
        $sequence->sequenceId = $sequenceId;

        return $sequence;
    }

    public static function createAction(
        string $action,
        ?Sequence $nextAction,
        string $flowId,
        string $sequenceId,
        array $config = [],
        ?string $appFlowActionId = null
    ): ActionSequence {
        $sequence = new ActionSequence();
        $sequence->action = $action;
        $sequence->config = $config;
        $sequence->nextAction = $nextAction;
        $sequence->flowId = $flowId;
        $sequence->sequenceId = $sequenceId;
        $sequence->appFlowActionId = $appFlowActionId;

        return $sequence;
    }
}
