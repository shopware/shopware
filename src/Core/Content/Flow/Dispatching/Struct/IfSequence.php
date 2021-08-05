<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

/**
 * @internal API
 */
class IfSequence extends Sequence
{
    public string $ruleId;

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;
}
