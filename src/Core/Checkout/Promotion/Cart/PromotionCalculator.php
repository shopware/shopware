<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
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
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\PriceDefinitionNotValidForDiscountTypeException;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
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

    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    public function __construct(
        AmountCalculator $amountCalculator,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        QuantityPriceCalculator $quantityPriceCalculator
    ) {
        $this->amountCalculator = $amountCalculator;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
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

        /* @var LineItem $discountLineItem */
        foreach ($discountLineItems as $discountItem) {
            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            if ($discountItem->getPayloadValue('discountScope') !== PromotionDiscountEntity::SCOPE_CART) {
                continue;
            }

            // we have to verify if the line item is still valid depending on
            // the added requirements and conditions.
            if (!$this->isRequirementValid($discountItem, $calculated, $context)) {
                continue;
            }

            /** @var string $discountType */
            $discountType = $discountItem->getPayloadValue('discountType');

            $discountItems = new LineItemCollection();

            switch ($discountType) {
                case PromotionDiscountEntity::TYPE_FIXED:
                    $this->calculateFixedDiscount($discountItem, $original, $calculated, $discountItems, $context);
                    break;
                default:
                    $discountItem = $this->calculateStandardDiscount($discountItem, $calculated, $context);

                    // if we our price is 0,00 because of whatever reason, make sure to skip it.
                    // this can be if the price-definition filter is none,
                    // or if a fixed price is set to the price of the product itself.
                    if (
                        $discountItem->getType() !== PromotionDiscountEntity::SCOPE_DELIVERY
                        && ($discountItem->getPrice() === null || abs($discountItem->getPrice()->getTotalPrice()) === 0.0)
                    ) {
                        continue 2;
                    }

                    $discountItems->add($discountItem);
            }

            if ($discountItems->count() === 0) {
                continue;
            }

            // add discount lineItems to cart
            $calculated->addLineItems($discountItems);

            // calculate cart to get correct prices for next iterations
            $this->calculateCart($calculated, $context);
        }
    }

    /**
     * calculates fixed discounts dynamically. Our product should have a fixed price, the discount
     * has to be calculated depending on the unit price of the product
     *
     * @throws PriceDefinitionNotValidForDiscountTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function calculateFixedDiscount(LineItem $discount, Cart $original, Cart $calculated, LineItemCollection $discountLineItems, SalesChannelContext $context): void
    {
        /** @var LineItemCollection $cartLineItems */
        $cartLineItems = $calculated->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        // do only select these lineitems that the discount filter matches
        $eligibleLineItems = $this->getEligibleDiscountLineItems($discount, $cartLineItems, $context);

        // get the cart total price => discount may never be higher than this value
        /** @var float $maxDiscountValue */
        $maxDiscountValue = $calculated->getPrice()->getTotalPrice();

        if ($maxDiscountValue < 0.01) {
            return;
        }

        $discountedPriceValue = 0.0;

        $absolutePriceDefinition = $discount->getPriceDefinition();

        if (!$absolutePriceDefinition instanceof AbsolutePriceDefinition) {
            throw new InvalidPriceDefinitionException($discount);
        }

        $fixedProductPrice = (float) abs($absolutePriceDefinition->getPrice());

        // iterate over every lineItem that may be discounted and create a separate discount for each
        /** @var LineItem $lineItem */
        foreach ($eligibleLineItems as $lineItem) {
            // it may occure that other discount with higher priority discount more than cart value
            // if this happens we may not discount this lineItem
            if ($discountedPriceValue >= $maxDiscountValue) {
                continue;
            }

            $unitPrice = $lineItem->getPrice()->getUnitPrice();

            // if unitPrice of product is higher than the fixed price we may reduce to fixed price
            if ($unitPrice > $fixedProductPrice) {
                $quantity = $lineItem->getPrice()->getQuantity();
                if ($quantity <= 0) {
                    continue;
                }

                // check if discount exceeds or not, beware of quantity
                $discountUnitPrice = $unitPrice - $fixedProductPrice;

                $totalDiscountPrice = $discountUnitPrice * $quantity;

                if ($totalDiscountPrice > $maxDiscountValue) {
                    $discountUnitPrice = $maxDiscountValue / $quantity;
                }

                $discountedPriceValue += ($discountUnitPrice * $quantity);

                $this->createFixedPriceDiscount($discount, $lineItem, $discountUnitPrice, $discountLineItems, $context, $original);
            }
        }
    }

    /**
     * create a discount lineItem for a product lineItem and add this discount to the
     * collection $discountLineItems
     *
     * @throws PriceDefinitionNotValidForDiscountTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    private function createFixedPriceDiscount(
        LineItem $discount,
        LineItem $product,
        float $discountUnitPrice,
        LineItemCollection $discountLineItems,
        SalesChannelContext $context,
        Cart $original
    ): void {
        $quantity = $product->getQuantity();
        /** @var PriceDefinitionInterface|null $productPriceDefinition */
        $productPriceDefinition = $product->getPriceDefinition();

        if (!$productPriceDefinition instanceof QuantityPriceDefinition) {
            throw new PriceDefinitionNotValidForDiscountTypeException('Product are expected to have a QuantityPriceDefinition!');
        }

        // a fixed price discount may discount more than one product lineItem
        // the problem is, we need an unique lineItem id for our discount
        // as we may not create a new uuid each time we calculate, we
        // have to lookup our original cart if we have created a uuid before
        // if we don't use same uuid we couldn't update, delete any lineItem
        // because we would get a new uuid in the next calculation process
        $uuid = $this->getCartLineItemUuid($discount->getId(), $product->getId(), $original);

        $taxRules = $productPriceDefinition->getTaxRules();

        $discountPriceDefinition = new QuantityPriceDefinition((-1 * $discountUnitPrice), $taxRules, $productPriceDefinition->getPrecision(), $quantity, true);

        $promotionItem = new LineItem($uuid, PromotionProcessor::LINE_ITEM_TYPE, $discount->getReferencedId(), $quantity);
        $promotionItem->setLabel($discount->getLabel() . ' (' . $product->getLabel() . ')');
        $promotionItem->setDescription($discount->getLabel() . ' (' . $product->getLabel() . ')');
        $promotionItem->setGood(false);
        $promotionItem->setRemovable(true);
        $promotionItem->setPriceDefinition($discountPriceDefinition);
        $promotionItem->setPrice($this->quantityPriceCalculator->calculate($discountPriceDefinition, $context));

        /** @var array $payload */
        $payload = $discount->getPayload();

        // add the discounted product id to payload of the discount item
        $payload['productLineItemId'] = $product->getId();
        $promotionItem->setPayload($payload);

        $discountLineItems->add($promotionItem);
    }

    /**
     * lookup the cart if we have a discount for a product in the cart.
     * If yes => take this uuid
     * If no => create a new uuid
     */
    private function getCartLineItemUuid(string $discountId, string $productId, Cart $original): string
    {
        $filteredPromotionLineItem = $original->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->filter(function ($lineItem) use ($discountId, $productId) {
            if (!$lineItem->hasPayloadValue('discountId') || !$lineItem->hasPayloadValue('productLineItemId')) {
                return false;
            }

            if ($lineItem->getPayloadValue('discountId') === $discountId && $lineItem->getPayloadValue('productLineItemId') === $productId) {
                return true;
            }

            return false;
        });

        if ($filteredPromotionLineItem->count() < 1) {
            return Uuid::randomHex();
        }

        return $filteredPromotionLineItem->first()->getId();
    }

    /**
     * create discount for absolute and percentage discount type
     *
     * @throws InvalidPriceDefinitionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function calculateStandardDiscount(LineItem $discount, Cart $calculated, SalesChannelContext $context): LineItem
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

                // now that we have calculated our percentage value
                // verify if we have a maximum value for this  discount
                // if so, and if it is lower than our actual one, we have to switch over
                // to an absolute price definition of that fixed value.
                /** @var CalculatedPrice $discountPrice */
                $discountPrice = $this->fixPercentageMaxValue($discount, $discountPrice, $priceCollection, $context);
                break;
        }

        // set our new calculated and correct
        // price for our discount line item
        if ($discountPrice instanceof CalculatedPrice) {
            $discount->setPrice($discountPrice);
        }

        return $discount;
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

        // In very rare scenarios where the line item total price is 0,00.
        // Thus we would get a division by zero problem,
        // so lets check for a price of 0,00 and then skip a price definition fix.
        if ((float) $lineItemsTotalPrice === 0.0) {
            return;
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
     * This function verifies if we have a maximum value for our
     * percentage discount and if it would be lower than our current discount.
     * If so, it applies an absolute discount with that value and returns the calculated price.
     */
    private function fixPercentageMaxValue(LineItem $discount, CalculatedPrice $calculatedPrice, PriceCollection $priceCollection, SalesChannelContext $context): CalculatedPrice
    {
        // if we dont have a max value
        // just return our origin price
        if (!$discount->hasPayloadValue('maxValue')) {
            return $calculatedPrice;
        }

        /** @var string $stringValue */
        $stringValue = $discount->getPayload()['maxValue'];

        // if we have an empty string value
        // then we convert it to 0.00 when casting it,
        // thus we create an early return
        if (trim($stringValue) === '') {
            return $calculatedPrice;
        }

        /** @var float $maxValue */
        $maxValue = (float) $stringValue;

        // check if our max value is lower than our currently calculated price
        if (abs($calculatedPrice->getTotalPrice()) > $maxValue) {
            /** @var PercentagePriceDefinition $percentageDefinition */
            $percentageDefinition = $discount->getPriceDefinition();

            /** @var AbsolutePriceDefinition $absoluteDefinition */
            $absoluteDefinition = new AbsolutePriceDefinition(
                -abs($maxValue),
                $percentageDefinition->getPrecision(),
                $this->addProductFilter($percentageDefinition)
            );

            $discount->setPriceDefinition($absoluteDefinition);

            // try to get a new calculated price
            // by using our absolute calculator this time with
            // our new definition
            $calculatedPrice = $this->absolutePriceCalculator->calculate($absoluteDefinition->getPrice(), $priceCollection, $context);
        }

        return $calculatedPrice;
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
