<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Framework\Rule\Collector\CollectConditionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartConditionCollector implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            CollectConditionEvent::NAME => 'collectConditions',
        ];
    }

    public function collectConditions(CollectConditionEvent $collectConditionEvent): void
    {
        $collectConditionEvent->addClasses(
            CartAmountRule::class,
            GoodsCountRule::class,
            GoodsPriceRule::class,
            LineItemOfTypeRule::class,
            LineItemRule::class,
            LineItemsInCartRule::class,
            LineItemTotalPriceRule::class,
            LineItemUnitPriceRule::class,
            LineItemWithQuantityRule::class
        );
    }
}
