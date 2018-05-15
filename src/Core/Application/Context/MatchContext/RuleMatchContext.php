<?php declare(strict_types=1);

namespace Shopware\Application\Context\MatchContext;

use Shopware\Application\Context\Struct\StorefrontContext;

abstract class RuleMatchContext
{
    abstract public function getContext(): StorefrontContext;
}
