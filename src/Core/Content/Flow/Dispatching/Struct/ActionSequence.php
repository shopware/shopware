<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

/**
 * @internal (flag:FEATURE_NEXT_8225) - Internal used for FlowBuilder feature
 */
class ActionSequence extends Sequence
{
    public string $action;

    public array $config = [];

    public ?Sequence $nextAction = null;
}
