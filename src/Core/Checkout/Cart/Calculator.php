<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class Calculator
{
    /**
     * @var QuantityPriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    public function __construct(
        QuantityPriceCalculator $priceCalculator,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    public function calculate(Cart $cart, CheckoutContext $context): LineItemCollection
    {
        return $this->calculateLineItems($cart, $cart->getLineItems(), $context);
    }

    private function calculateLineItems(Cart $cart, LineItemCollection $lineItems, CheckoutContext $context): LineItemCollection
    {
        $lineItems->sort(
            function (LineItem $a, LineItem $b) {
                return $b->getPriority() <=> $a->getPriority();
            }
        );

        $calculated = new LineItemCollection();

        /** @var LineItemCollection $lineItems */
        /** @var LineItem $original */
        foreach ($lineItems as $original) {
            $lineItem = LineItem::createFrom($original);

            try {
                $price = $this->calculatePrice($cart, $lineItem, $context, $calculated);
            } catch (\Exception $e) {
                $cart->getLineItems()->remove($lineItem->getKey());
                continue;
            }

            $lineItem->setPrice($price);

            $calculated->add($lineItem);
        }

        return $calculated;
    }

    private function filterLineItems(LineItemCollection $calculated, ?Rule $filter, CheckoutContext $context): LineItemCollection
    {
        if (!$filter) {
            return $calculated;
        }

        return $calculated->filter(
            function (LineItem $lineItem) use ($filter, $context) {
                $match = $filter->match(
                    new LineItemScope($lineItem, $context)
                );

                return $match->matches();
            }
        );
    }

    private function calculatePrice(Cart $cart, LineItem $lineItem, CheckoutContext $context, LineItemCollection $calculated): Price
    {
        if ($lineItem->getPrice()) {
            return $lineItem->getPrice();
        }

        if ($lineItem->getChildren()) {
            $children = $this->calculateLineItems($cart, $lineItem->getChildren(), $context);

            $lineItem->setChildren($children);

            return $children->getPrices()->sum();
        }

        $definition = $lineItem->getPriceDefinition();

        if ($definition instanceof AbsolutePriceDefinition) {
            //reduce line items for provided filter
            $prices = $this->filterLineItems($calculated, $definition->getFilter(), $context)
                ->getPrices();

            return $this->absolutePriceCalculator->calculate($definition->getPrice(), $prices, $context);
        }

        if ($definition instanceof PercentagePriceDefinition) {
            //reduce line items for provided filter
            $prices = $this->filterLineItems($calculated, $definition->getFilter(), $context)
                ->getPrices();

            return $this->percentagePriceCalculator->calculate($definition->getPercentage(), $prices, $context);
        }

        if ($definition instanceof QuantityPriceDefinition) {
            return $this->priceCalculator->calculate($definition, $context);
        }

        throw new \RuntimeException(sprintf('No price definition and no price given for elements %s', $lineItem->getKey()));
    }
}
