<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractRuleLoader
{
    abstract public function getDecorated(): AbstractRuleLoader;

    abstract public function load(Context $context): RuleCollection;
}
