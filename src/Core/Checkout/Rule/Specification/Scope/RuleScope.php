<?php declare(strict_types=1);

namespace Shopware\Checkout\Rule\Specification\Scope;

use Shopware\Checkout\CustomerContext;

abstract class RuleScope
{
    abstract public function getContext(): CustomerContext;
}
