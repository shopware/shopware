<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class Calculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly QuantityPriceCalculator $quantityPriceCalculator,
        private readonly PercentagePriceCalculator $percentagePriceCalculator,
        private readonly AbsolutePriceCalculator $absolutePriceCalculator
    ) {
    }

    public function calculate(LineItemCollection $lineItems, SalesChannelContext $context, CartBehavior $behavior): LineItemCollection
    {
        return $this->calculateLineItems($lineItems, $context, $behavior);
    }

    private function calculateLineItems(LineItemCollection $lineItems, SalesChannelContext $context, CartBehavior $behavior): LineItemCollection
    {
        $workingSet = clone $lineItems;
        $workingSet->sortByPriority();

        $calculated = new LineItemCollection();

        foreach ($workingSet as $original) {
            $lineItem = LineItem::createFromLineItem($original);

            $price = $this->calculatePrice($lineItem, $context, $calculated, $behavior);

            $lineItem->setPrice($price);

            $calculated->add($lineItem);
        }

        return $calculated;
    }

    private function filterLineItems(LineItemCollection $calculated, ?Rule $filter, SalesChannelContext $context): LineItemCollection
    {
        if (!$filter) {
            return $calculated;
        }

        return $calculated->filter(
            function (LineItem $lineItem) use ($filter, $context) {
                $match = $filter->match(
                    new LineItemScope($lineItem, $context)
                );

                return $match;
            }
        );
    }

    private function calculatePrice(LineItem $lineItem, SalesChannelContext $context, LineItemCollection $calculated, CartBehavior $behavior): CalculatedPrice
    {
        if ($lineItem->hasChildren()) {
            $children = $this->calculateLineItems($lineItem->getChildren(), $context, $behavior);

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
            $definition->setQuantity($lineItem->getQuantity());

            return $this->quantityPriceCalculator->calculate($definition, $context);
        }

        throw CartException::missingLineItemPrice($lineItem->getId());
    }
}
