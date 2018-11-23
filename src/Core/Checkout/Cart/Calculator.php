<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\MissingLineItemPriceException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\PriceCalculatorInterface;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
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
     * @var PriceCalculatorInterface[]
     */
    private $calculators;

    /**
     * @param QuantityPriceCalculator    $priceCalculator
     * @param PriceCalculatorInterface[] $calculators
     */
    public function __construct(
        QuantityPriceCalculator $priceCalculator,
        iterable $calculators
    ) {
        $this->priceCalculator = $priceCalculator;
        $this->calculators = $calculators;
    }

    public function calculate(Cart $cart, CheckoutContext $context): LineItemCollection
    {
        return $this->calculateLineItems($cart, $cart->getLineItems(), $context);
    }

    private function calculateLineItems(Cart $cart, LineItemCollection $lineItems, CheckoutContext $context): LineItemCollection
    {
        $lineItems->sortByPriority();

        $calculated = new LineItemCollection();

        foreach ($lineItems as $original) {
            $lineItem = LineItem::createFromLineItem($original);

            try {
                $price = $this->calculatePrice($cart, $lineItem, $context, $calculated);
            } catch (\Exception $e) {
                // todo line item silently removed if an error occurs
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
        if ($lineItem->hasChildren()) {
            $children = $this->calculateLineItems($cart, $lineItem->getChildren(), $context);

            $lineItem->setChildren($children);

            return $children->getPrices()->sum();
        }

        $definition = $lineItem->getPriceDefinition();

        foreach ($this->calculators as $calculator) {
            if ($calculator->supports($definition)) {
                //reduce line items for provided filter
                $prices = $this->filterLineItems($calculated, $definition->getFilter(), $context)
                    ->getPrices();

                return $calculator->calculate($definition, $prices, $context);
            }
        }

        if ($definition instanceof QuantityPriceDefinition) {
            $definition->setQuantity($lineItem->getQuantity());

            return $this->priceCalculator->calculate($definition, $context);
        }

        throw new MissingLineItemPriceException($lineItem->getKey());
    }
}
