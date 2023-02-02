<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\RulesMatcher;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupDefinition;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractAnyRuleLineItemMatcher
{
    abstract public function getDecorated(): AbstractAnyRuleLineItemMatcher;

    abstract public function isMatching(LineItemGroupDefinition $groupDefinition, LineItem $item, SalesChannelContext $context): bool;
}
