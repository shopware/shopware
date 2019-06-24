<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Cart Promotion Calculator
 */
class PromotionCalculator
{
    /**
     * @var AmountCalculator
     */
    private $amountCalculator;

    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    public function __construct(AmountCalculator $amountCalculator, PercentagePriceCalculator $percentagePriceCalculator, AbsolutePriceCalculator $absolutePriceCalculator)
    {
        $this->amountCalculator = $amountCalculator;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    /**
     * Calculates the cart including the new discount line items.
     * The calculation process will first determine the correct values for
     * the different discount line item types (percentage, absolute, ...) and then
     * recalculate the whole cart with these new items.
     *
     * @throws InvalidPriceDefinitionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    public function calculate(LineItemCollection $discountLineItems, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behaviour): void
    {
        // @todo order $discountLineItems by priority

        /** @var LineItem $discountLineItem */
        foreach ($discountLineItems as $discountLineItem) {
            // we have to verify if the line item is still valid depending on
            // the added requirements and conditions.
            if (!$this->isRequirementValid($discountLineItem, $calculated, $context)) {
                continue;
            }

            $this->addStandardDiscount($discountLineItem, $calculated, $context);
        }
    }

    /**
     * @throws InvalidPriceDefinitionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function addStandardDiscount(LineItem $discount, Cart $calculated, SalesChannelContext $context): void
    {
        /** @var LineItemCollection $cartLineItems */
        $cartLineItems = $calculated->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        // do only select line items that the discount filter matches
        /** @var LineItem[] $eligibleLineItems */
        $eligibleLineItems = $this->getEligibleDiscountLineItems($discount, $cartLineItems, $context);

        // get the cart total price => discount must not be higher than this value
        /** @var float $maxDiscountValue */
        $maxDiscountValue = $calculated->getPrice()->getTotalPrice();

        // The calculator expects all prices in a collection => collect them in priceCollection
        $priceCollection = new PriceCollection();

        // iterate over all filtered lineItems, get the prices and add them to the price collection
        // calculate the total price of all filtered lineItems
        /** @var LineItem $lineItem */
        foreach ($eligibleLineItems as $lineItem) {
            /** @var CalculatedPrice $calculatedPrice */
            $calculatedPrice = $lineItem->getPrice();

            $priceCollection->add($calculatedPrice);
        }

        $lineItemsTotalPrice = $priceCollection->sum()->getTotalPrice();

        /** @var string $discountType */
        $discountType = $discount->getPayloadValue('discountType');

        // we add the calculated price in discountCalculatedPrice
        $discountPrice = null;

        switch ($discountType) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                // we have to fix our absolute price definition
                // if it exceed our maximum allowed discount
                $this->fixAbsolutePriceDefinition($discount, $maxDiscountValue);

                /** @var AbsolutePriceDefinition $definition */
                $definition = $discount->getPriceDefinition();

                /** @var CalculatedPrice $discountPrice */
                $discountPrice = $this->absolutePriceCalculator->calculate(
                    $definition->getPrice(),
                    $priceCollection,
                    $context
                );
                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                // we have to fix our percentage price definition
                // if it exceed our maximum allowed discount
                $this->fixPercentagePriceDefinition($discount, $maxDiscountValue, $lineItemsTotalPrice);

                /** @var PercentagePriceDefinition $definition */
                $definition = $discount->getPriceDefinition();

                /** @var CalculatedPrice $discountPrice */
                $discountPrice = $this->percentagePriceCalculator->calculate(
                    $definition->getPercentage(),
                    $priceCollection,
                    $context
                );
                break;
        }

        // if we haven't calculated anything,
        // we won't add anything on calculated cart
        if (!$discountPrice instanceof CalculatedPrice) {
            return;
        }

        // set our new calculated and correct
        // price for our discount line item
        $discount->setPrice($discountPrice);

        // add the new line item to our cart
        // and calculate it after each line item!
        $calculated->addLineItems(new LineItemCollection([$discount]));
        $this->calculateCart($calculated, $context);
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    private function fixAbsolutePriceDefinition(LineItem $discount, float $maxDiscountValue): void
    {
        $originalPriceDefinition = $discount->getPriceDefinition();

        if (!$originalPriceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount);
        }

        /** @var float $discountPrice */
        $discountPrice = $originalPriceDefinition->getPrice();

        // if our price is larger than the max discount value,
        // then use the max discount value as negative discount,
        // otherwise simply use the calculated price
        if (abs($discountPrice) > $maxDiscountValue) {
            $discountPrice = -($maxDiscountValue);
        }

        /** @var PriceDefinitionInterface $actualPriceDefinition */
        $actualPriceDefinition = new AbsolutePriceDefinition(
            $discountPrice,
            $originalPriceDefinition->getPrecision(),
            $this->addProductFilter($originalPriceDefinition)
        );

        // set the new and fixed price definition
        $discount->setPriceDefinition($actualPriceDefinition);
    }

    /**
     * @throws InvalidPriceDefinitionException
     */
    private function fixPercentagePriceDefinition(LineItem $discount, float $maxDiscountValue, float $lineItemsTotalPrice): void
    {
        $originalPriceDefinition = $discount->getPriceDefinition();

        if (!$originalPriceDefinition instanceof PercentagePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount);
        }

        // the discount value may never push the cart total price to a value lower than zero.
        // Therefore we reduce the discount percentage rate to a value that fits this requirement
        $maxPercentageRate = $maxDiscountValue / $lineItemsTotalPrice;
        $percentageRate = abs($originalPriceDefinition->getPercentage());

        if (($percentageRate / 100) > $maxPercentageRate) {
            $percentageRate = $maxPercentageRate * 100;
        }

        /** @var float $calculatedPercentageRate */
        $calculatedPercentageRate = -$percentageRate;

        /** @var PriceDefinitionInterface $actualPriceDefinition */
        $actualPriceDefinition = new PercentagePriceDefinition(
            $calculatedPercentageRate,
            $originalPriceDefinition->getPrecision(),
            $this->addProductFilter($originalPriceDefinition)
        );

        $discount->setPriceDefinition($actualPriceDefinition);
    }

    /**
     * Make sure that we also add a filter to only create
     * discounts for product items.
     * Thus we either create that filter, or add the
     * filter to an existing one.
     */
    private function addProductFilter(PriceDefinitionInterface $priceDefinition): ?Rule
    {
        if (!method_exists($priceDefinition, 'getFilter')) {
            return null;
        }

        $productTypeFilter = new LineItemOfTypeRule(Rule::OPERATOR_EQ, LineItem::PRODUCT_LINE_ITEM_TYPE);

        /** @var Rule|null $filter */
        $filter = $priceDefinition->getFilter();

        // if we already have a filter rule
        // then wrap both in an additional AND rule
        if ($filter instanceof Rule) {
            $newFilter = new AndRule();
            $newFilter->addRule($filter);
            $newFilter->addRule($productTypeFilter);

            return $newFilter;
        }

        return $productTypeFilter;
    }

    /**
     * returns only lineItems that match the discount filter
     */
    private function getEligibleDiscountLineItems(LineItem $discount, LineItemCollection $calculated, SalesChannelContext $context): array
    {
        /** @var PriceDefinitionInterface $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        /** @var array $foundItems */
        $foundItems = [];

        /** @var LineItem $cartLineItem */
        foreach ($calculated as $cartLineItem) {
            // if our price definition has a filter rule
            // then extract it, and check if it matches
            if (!method_exists($priceDefinition, 'getFilter')) {
                $foundItems[] = $cartLineItem;
                continue;
            }

            /** @var Rule|null $filter */
            $filter = $priceDefinition->getFilter();

            if (!$filter instanceof Rule) {
                $foundItems[] = $cartLineItem;
                continue;
            }

            $scope = new LineItemScope($cartLineItem, $context);

            if ($filter->match($scope)) {
                $foundItems[] = $cartLineItem;
            }
        }

        return $foundItems;
    }

    /**
     * Validates the included requirements and returns if the
     * line item is allowed to be added to the actual cart.
     */
    private function isRequirementValid(LineItem $lineItem, Cart $calculated, SalesChannelContext $context): bool
    {
        // if we dont have any requirement
        // it's obviously valid
        if (!$lineItem->getRequirement()) {
            return true;
        }

        $scopeWithoutLineItem = new CartRuleScope($calculated, $context);

        return $lineItem->getRequirement()->match($scopeWithoutLineItem);
    }

    /**
     * calculate the cart sum
     */
    private function calculateCart(Cart $cart, SalesChannelContext $context): void
    {
        $amount = $this->amountCalculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );

        $cart->setPrice($amount);
    }
}
