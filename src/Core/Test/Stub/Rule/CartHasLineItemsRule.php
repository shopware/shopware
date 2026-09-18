<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Rule;

use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;

class CartHasLineItemsRule extends Rule
{
    final public const RULE_NAME = 'cartHasLineItems';

    public function match(RuleScope $matchContext): bool
    {
        return $matchContext instanceof CartRuleScope && $matchContext->getCart()->getLineItems()->count() > 0;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
