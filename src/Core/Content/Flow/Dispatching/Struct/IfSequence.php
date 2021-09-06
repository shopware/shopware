<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

/**
 * @internal (flag:FEATURE_NEXT_8225) - Internal used for FlowBuilder feature
 */
class IfSequence extends Sequence
{
    public string $ruleId;

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;
}
