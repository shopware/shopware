<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Exception\MissingLineItemPriceException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Calculator
{
    /**
     * @var QuantityPriceCalculator
     */
    protected $quantityPriceCalculator;

    /**
     * @var PercentagePriceCalculator
     */
    protected $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    protected $absolutePriceCalculator;

    /**
     * @var AmountCalculator
     */
    protected $amountCalculator;

    public function __construct(
        QuantityPriceCalculator $quantityPriceCalculator,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        AmountCalculator $amountCalculator
    ) {
        $this->quantityPriceCalculator = $quantityPriceCalculator;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->amountCalculator = $amountCalculator;
    }

    public function calculate(Cart $cart, SalesChannelContext $context, CartBehavior $behavior): LineItemCollection
    {
        return $this->calculateLineItems($cart, $cart->getLineItems(), $context, $behavior);
    }

    private function calculateLineItems(Cart $cart, LineItemCollection $lineItems, SalesChannelContext $context, CartBehavior $behavior): LineItemCollection
    {
        $lineItems->sortByPriority();

        $calculated = new LineItemCollection();

        foreach ($lineItems as $original) {
            $lineItem = LineItem::createFromLineItem($original);

            if (!$this->isValid($lineItem, $calculated, $context, $behavior)) {
                $cart->getLineItems()->remove($lineItem->getKey());
                continue;
            }

            try {
                $price = $this->calculatePrice($cart, $lineItem, $context, $calculated, $behavior);
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

    private function calculatePrice(Cart $cart, LineItem $lineItem, SalesChannelContext $context, LineItemCollection $calculated, CartBehavior $behavior): CalculatedPrice
    {
        if ($lineItem->hasChildren()) {
            $children = $this->calculateLineItems($cart, $lineItem->getChildren(), $context, $behavior);

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

        throw new MissingLineItemPriceException($lineItem->getKey());
    }

    private function isValid(LineItem $lineItem, LineItemCollection $calculated, SalesChannelContext $context, CartBehavior $behavior): bool
    {
        if (!$lineItem->getRequirement() || $behavior->isRecalculation()) {
            return true;
        }

        $cart = new Cart('validate', 'validate');
        $cart->setLineItems($calculated);

        $cart->setPrice(
            $this->amountCalculator->calculate(
                $calculated->getPrices(),
                new PriceCollection(),
                $context
            )
        );

        $scope = new CartRuleScope($cart, $context);

        return $lineItem->getRequirement()->match($scope);
    }
}
