<?php declare(strict_types=1);

namespace Shopware\Context\MatchContext;

use Shopware\Context\Struct\StorefrontContext;

abstract class RuleMatchContext
{
    abstract public function getContext(): StorefrontContext;
}
