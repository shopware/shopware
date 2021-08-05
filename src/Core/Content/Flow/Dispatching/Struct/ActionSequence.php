<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

/**
 * @internal API
 */
class ActionSequence extends Sequence
{
    public string $action;

    public array $config = [];

    public ?Sequence $nextAction = null;
}
