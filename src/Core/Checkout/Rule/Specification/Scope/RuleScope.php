<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Specification\Scope;

use Shopware\Application\Context\Struct\StorefrontContext;

abstract class RuleScope
{
    abstract public function getContext(): StorefrontContext;
}
