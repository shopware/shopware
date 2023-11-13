<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('services-settings')]
class IfSequence extends Sequence
{
    public string $ruleId;

    public ?Sequence $falseCase = null;

    public ?Sequence $trueCase = null;
}
