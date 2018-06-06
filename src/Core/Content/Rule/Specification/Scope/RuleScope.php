<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Specification\Scope;

use Shopware\Core\Checkout\CustomerContext;

abstract class RuleScope
{
    abstract public function getContext(): CustomerContext;
}
