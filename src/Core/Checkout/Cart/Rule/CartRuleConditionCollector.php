<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Collector\RuleConditionCollectorInterface;

class CartRuleConditionCollector implements RuleConditionCollectorInterface
{
    public function getClasses(): array
    {
        return [
            CartAmountRule::class,
            GoodsCountRule::class,
            GoodsPriceRule::class,
            LineItemOfTypeRule::class,
            LineItemRule::class,
            LineItemsInCartRule::class,
            LineItemTotalPriceRule::class,
            LineItemUnitPriceRule::class,
            LineItemWithQuantityRule::class,
        ];
    }
}
