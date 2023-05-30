<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Calculates discounts on deliveries
 * as calculation base we are always taking the delivery costs coming from the delivery calculator
 * this means if we have an absolute and percentage discount, the percentage discount is always
 * calculated with the deliveries coming from DeliveryCalculator even if absolute discounts have
 * reduced the delivery costs before
 * Shippingcosts 100
 * Absolute discount is 10 => Shippingcosts = 90
 * Percentage discount is 30 => Shippingcosts = 60 (Shippingcosts = 100 - (10 + 100 * 0.3))
 */
#[Package('checkout')]
class PromotionDeliveryCalculator
{
    use PromotionCartInformationTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly QuantityPriceCalculator $quantityPriceCalculator,
        private readonly PercentagePriceCalculator $percentagePriceCalculator,
        private readonly PromotionItemBuilder $builder
    ) {
    }

    /**
     * Calculates discounts for delivery costs of cart
     * The calculation process will first determine if we have a delivery discount
     * (we use the same collector for all promotions)
     * after that it is calculating the shipping costs respecting absolute, fixed or percentage discounts
     *
     * @throws InvalidPriceDefinitionException
     * @throws CartException
     */
    public function calculate(LineItemCollection $discountLineItems, Cart $original, Cart $toCalculate, SalesChannelContext $context): void
    {
        $notDiscountedDeliveriesValue = $toCalculate->getDeliveries()->getShippingCosts()->sum()->getTotalPrice();

        // reduce discount lineItems if fixed price discounts are in collection
        $checkedDiscountLineItems = $this->reduceDiscountLineItemsIfFixedPresent($discountLineItems);

        $exclusions = $this->buildExclusions($checkedDiscountLineItems);

        foreach ($checkedDiscountLineItems as $discountItem) {
            if ($notDiscountedDeliveriesValue <= 0.0) {
                continue;
            }

            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            if ($discountItem->getPayloadValue('discountScope') !== PromotionDiscountEntity::SCOPE_DELIVERY) {
                continue;
            }

            if (!$this->isRequirementValid($discountItem, $toCalculate, $context)) {
                // hide the notEligibleErrors on automatic discounts
                if (!$this->isAutomaticDisount($discountItem)) {
                    $this->addPromotionNotEligibleError($discountItem->getLabel() ?? $discountItem->getId(), $toCalculate);
                }

                continue;
            }

            // if promotion is on exclusions stack it is ignored
            if (!$discountItem->hasPayloadValue('promotionId')) {
                continue;
            }

            $promotionId = $discountItem->getPayloadValue('promotionId');

            if (\array_key_exists($promotionId, $exclusions)) {
                $toCalculate->addErrors(new PromotionNotEligibleError($discountItem->getDescription() ?? $discountItem->getId()));

                continue;
            }

            $deliveryItemAdded = $this->calculateDeliveryPromotion($toCalculate, $discountItem, $context, $notDiscountedDeliveriesValue);

            if ($deliveryItemAdded) {
                // ensure that a lineItem will be added to cart if a discount has been added
                $this->addFakeLineitem($toCalculate, $discountItem, $context);
                $this->addPromotionAddedNotice($original, $toCalculate, $discountItem);
            } else {
                $this->addPromotionDeletedNotice($original, $toCalculate, $discountItem);
            }
        }
    }

    /**
     * This function builds a complete list of promotions
     * that are excluded somehow.
     * The validation which one to take will be done later.
     *
     * @return array<mixed, boolean>
     */
    private function buildExclusions(LineItemCollection $discountLineItems): array
    {
        // array that holds all excluded promotion ids.
        // if a promotion has exclusions they are added on the stack
        $exclusions = [];

        foreach ($discountLineItems as $discountItem) {
            // if we dont have a scope
            // then skip it, it might not belong to us
            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            // if promotion is on exclusions stack it is ignored
            if ($discountItem->hasPayloadValue('promotionId')) {
                $promotionId = $discountItem->getPayloadValue('promotionId');

                // if promotion is on exclusions stack it is ignored
                // this avoids cycles that both promotions exclude each other
                if (isset($exclusions[$promotionId])) {
                    continue;
                }
            }

            // add all exclusions to the stack
            foreach ($discountItem->getPayloadValue('exclusions') as $id) {
                $exclusions[$id] = true;
            }
        }

        return $exclusions;
    }

    /**
     * function reduces discountLineItems if a fixed price lineItem is in collection.
     * If fixed price discount lineItems are in collection:
     * a collection with only one lineItem is returned.
     * if there are more than one fixed price lineItems the lowest fixed price discount lineItem is returned
     * if no fixed price discount lineItems are in collection all discounts are returned
     */
    private function reduceDiscountLineItemsIfFixedPresent(LineItemCollection $discountLineItems): LineItemCollection
    {
        // filter all discountLineItems by scope delivery and type fixed price
        $fixedPricesDiscountLineItems = $discountLineItems->filter(function ($discountLineItem) {
            if (!$discountLineItem->hasPayloadValue('discountScope') || !$discountLineItem->hasPayloadValue('discountType')) {
                return false;
            }

            if ($discountLineItem->getPayloadValue('discountScope') !== PromotionDiscountEntity::SCOPE_DELIVERY) {
                return false;
            }

            if ($discountLineItem->getPayloadValue('discountType') === PromotionDiscountEntity::TYPE_FIXED_UNIT) {
                return true;
            }

            return false;
        });

        // if there are no fixed price lineItems we may return all discount line items and calculate them
        if ($fixedPricesDiscountLineItems->count() === 0) {
            return $discountLineItems;
        }

        // if there is one fixed price lineItem we return the filtered collection and calculate it
        if ($fixedPricesDiscountLineItems->count() === 1) {
            return $fixedPricesDiscountLineItems;
        }

        // if there are more than one fixed price lineitems in filtered collection
        // we are sorting all by lowest fixed price (lowest price to beginning)
        $fixedPricesDiscountLineItems->sort(function (LineItem $discountA, LineItem $discountB) {
            $priceDefA = $discountA->getPriceDefinition();
            $priceDefB = $discountB->getPriceDefinition();

            if (!$priceDefA instanceof AbsolutePriceDefinition) {
                throw new InvalidPriceDefinitionException((string) $discountA->getLabel(), $discountA->getReferencedId());
            }
            if (!$priceDefB instanceof AbsolutePriceDefinition) {
                throw new InvalidPriceDefinitionException((string) $discountB->getLabel(), $discountB->getReferencedId());
            }

            // NEXT-21735 - This is covered randomly
            // @codeCoverageIgnoreStart
            if ($priceDefA->getPrice() === $priceDefB->getPrice()) {
                return 0;
            }

            // Pricedefinition prices are always negative in discounts. To be compliant with
            // this
            if (abs($priceDefA->getPrice()) < abs($priceDefB->getPrice())) {
                return -1;
            }

            return 1;
            // @codeCoverageIgnoreEnd
        });

        // now we return a collection with the first price discountLineItem
        // of filtered and sorted discount lineItems
        return new LineItemCollection([$fixedPricesDiscountLineItems->first()]);
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
     * calculate the discount on deliveries for a discount
     */
    private function calculateDeliveryPromotion(Cart $toCalculate, LineItem $discountLineItem, SalesChannelContext $context, float $notDiscountedShippingCosts): bool
    {
        $deliveries = $toCalculate->getDeliveries();

        $discountType = $discountLineItem->getPayloadValue('discountType');

        $originalPriceDefinition = $discountLineItem->getPriceDefinition();

        $discountAdded = false;

        switch ($discountType) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                if (!$originalPriceDefinition instanceof AbsolutePriceDefinition) {
                    throw new InvalidPriceDefinitionException((string) $discountLineItem->getLabel(), $discountLineItem->getReferencedId());
                }

                $discountAdded = $this->calculateAbsolute($deliveries, $originalPriceDefinition, $context);

                break;
            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                if (!$originalPriceDefinition instanceof PercentagePriceDefinition) {
                    throw new InvalidPriceDefinitionException((string) $discountLineItem->getLabel(), $discountLineItem->getReferencedId());
                }

                $discountMaxValue = '';

                if ($discountLineItem->hasPayloadValue('maxValue')) {
                    $discountMaxValue = $discountLineItem->getPayloadValue('maxValue');
                }

                $discountAdded = $this->calculatePercentage($deliveries, $originalPriceDefinition, $context, $discountMaxValue);

                break;
            case PromotionDiscountEntity::TYPE_FIXED_UNIT:
                if (!$originalPriceDefinition instanceof AbsolutePriceDefinition) {
                    throw new InvalidPriceDefinitionException((string) $discountLineItem->getLabel(), $discountLineItem->getReferencedId());
                }

                $discountAdded = $this->calculateFixedDiscount($deliveries, $originalPriceDefinition, $context, $notDiscountedShippingCosts);

                break;
        }

        return $discountAdded;
    }

    /**
     * calculate the discount on all deliveries for a discount of type absolute
     */
    private function calculateAbsolute(DeliveryCollection $deliveries, AbsolutePriceDefinition $definition, SalesChannelContext $context): bool
    {
        $deliveryAdded = false;
        // get discount value
        $reduceValue = abs($definition->getPrice());

        // get shipping costs
        $maxReducedPrice = $deliveries->getShippingCosts()->sum()->getTotalPrice();

        // make sure that discount value is not higher than shipping costs, reduce them if necessary
        if ($reduceValue > $maxReducedPrice) {
            $reduceValue = $maxReducedPrice;
        }

        // now iterate over deliveries collection
        foreach ($deliveries as $delivery) {
            // if reduceValue is 0, we may not reduce shipping costs any more
            if ($reduceValue === 0) {
                continue;
            }

            // get shippingCost of the delivery
            $deliveryShippingPrice = $delivery->getShippingCosts()->getTotalPrice();

            // do not discount our previously added disounts (only these may be lower than 0)
            if ($deliveryShippingPrice < 0) {
                continue;
            }

            // beware that our discount may not reduce shipping costs beneath 0
            if ($reduceValue >= $deliveryShippingPrice) {
                // would reduce shipping costs under 0, only discount delivery shipping costs
                $calculateDefinition = new QuantityPriceDefinition(-1 * $deliveryShippingPrice, $delivery->getShippingCosts()->getTaxRules());
                // add a discount delivery item to the collection
                $this->addDiscountDeliveryItem($deliveries, $delivery, $this->quantityPriceCalculator->calculate($calculateDefinition, $context));
                $deliveryAdded = true;
                // reduce the discount value by shippingcosts (we may only reduce them to shippingcosts)
                $reduceValue -= $deliveryShippingPrice;

                continue;
            }

            // we may reduce shipping costs by reduceValue
            $calculateDefinition = new QuantityPriceDefinition(-1 * $reduceValue, $delivery->getShippingCosts()->getTaxRules());
            // add a discount delivery item to the collection
            $this->addDiscountDeliveryItem($deliveries, $delivery, $this->quantityPriceCalculator->calculate($calculateDefinition, $context));
            $deliveryAdded = true;
            // the amount of reduceValue has been added completely, set it to 0
            $reduceValue = 0;
        }

        return $deliveryAdded;
    }

    /**
     * calculate the discount on all deliveries for a discount of type percentage
     */
    private function calculatePercentage(DeliveryCollection $deliveries, PercentagePriceDefinition $definition, SalesChannelContext $context, string $maxValue): bool
    {
        $deliveryAdded = false;
        $reduceValue = abs($definition->getPercentage());

        // we may only discount the available shipping costs (these may be reduced by another discount before)
        $maxReducedPrice = $deliveries->getShippingCosts()->sum()->getTotalPrice();

        if ($maxValue !== '') {
            $castedMaxValue = (float) $maxValue;

            if ($castedMaxValue < $maxReducedPrice) {
                $maxReducedPrice = $castedMaxValue;
            }
        }

        foreach ($deliveries as $delivery) {
            // perecentage discounts always take the shippingCosts from DeliveryCalculator as base
            $price = $this->percentagePriceCalculator->calculate($reduceValue, new PriceCollection([$delivery->getShippingCosts()]), $context);

            $discountPrice = $price->getTotalPrice();

            // as percentage base may be higher than available shipping costs, the calculated price may be beneath the maxReducePrice
            // in this case we are reducing discount value to maxReducePrice
            if ($discountPrice > $maxReducedPrice) {
                $discountPrice = $maxReducedPrice;
            }

            $calculateDefinition = new QuantityPriceDefinition(-1 * $discountPrice, $delivery->getShippingCosts()->getTaxRules());
            // add a discount delivery item to the collection
            $this->addDiscountDeliveryItem($deliveries, $delivery, $this->quantityPriceCalculator->calculate($calculateDefinition, $context));
            $deliveryAdded = true;
        }

        return $deliveryAdded;
    }

    /**
     * calculate the discount on all deliveries for a discount of type fixed
     */
    private function calculateFixedDiscount(DeliveryCollection $deliveries, AbsolutePriceDefinition $definition, SalesChannelContext $context, float $notDiscountedShippingCosts): bool
    {
        $deliveryAdded = false;
        $fixedPrice = abs($definition->getPrice());

        // get shipping costs and set them as maximum value that may be discounted
        $maxReducedPrice = $deliveries->getShippingCosts()->sum()->getTotalPrice();

        if ($maxReducedPrice <= $fixedPrice) {
            return $deliveryAdded;
        }

        $dynamicDiscountPriceValue = $notDiscountedShippingCosts - $fixedPrice;

        if ($maxReducedPrice < $dynamicDiscountPriceValue) {
            $dynamicDiscountPriceValue = $maxReducedPrice - $fixedPrice;
        }

        foreach ($deliveries as $delivery) {
            // if the value of dynamicDiscountPriceValue is 0 we have created
            // enough delivery discounts before
            if ($dynamicDiscountPriceValue === 0) {
                continue;
            }

            // get shippingCost of the delivery
            $deliveryShippingPrice = $delivery->getShippingCosts()->getTotalPrice();

            // do not discount our previously added disounts (only these may be lower than 0)
            if ($deliveryShippingPrice < 0) {
                continue;
            }

            // beware that our discount may not reduce shipping costs beneath 0
            if ($dynamicDiscountPriceValue >= $deliveryShippingPrice) {
                // would reduce shipping costs under 0, only discount delivery shipping costs
                $calculateDefinition = new QuantityPriceDefinition(-1 * $deliveryShippingPrice, $delivery->getShippingCosts()->getTaxRules());
                // add a discount delivery item to the collection
                $this->addDiscountDeliveryItem($deliveries, $delivery, $this->quantityPriceCalculator->calculate($calculateDefinition, $context));
                $deliveryAdded = true;
                // reduce the discount value by shippingcosts (we may only reduce them to shippingcosts)
                $dynamicDiscountPriceValue -= $deliveryShippingPrice;

                continue;
            }

            // we may reduce shipping costs by reduceValue
            $calculateDefinition = new QuantityPriceDefinition(-1 * $dynamicDiscountPriceValue, $delivery->getShippingCosts()->getTaxRules());
            // add a discount delivery item to the collection
            $this->addDiscountDeliveryItem($deliveries, $delivery, $this->quantityPriceCalculator->calculate($calculateDefinition, $context));
            $deliveryAdded = true;
            // the amount of reduceValue has been added completely, set it to 0
            $dynamicDiscountPriceValue = 0;
        }

        return $deliveryAdded;
    }

    /**
     * add a discount delivery item to DeliveryCollection $deliveries
     */
    private function addDiscountDeliveryItem(DeliveryCollection $deliveries, Delivery $delivery, CalculatedPrice $price): void
    {
        if ($price->getTotalPrice() >= 0) {
            return;
        }

        $delivery = new Delivery(
            $delivery->getPositions(),
            $delivery->getDeliveryDate(),
            $delivery->getShippingMethod(),
            $delivery->getLocation(),
            $price
        );

        $deliveries->add($delivery);
    }

    /**
     * if we have a discount with scope delivery we add a lineItem in cart with price 0
     *
     * @throws CartException
     */
    private function addFakeLineitem(Cart $toCalculate, LineItem $discount, SalesChannelContext $context): void
    {
        // filter all cart line items with the code
        $lineItems = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->filter(fn ($discountLineItem) => $discountLineItem->getId() === $discount->getId());

        // if we have a line item in cart for this discount, it is already stored and we do not need to add
        // another lineitem
        if ($lineItems->count() > 0) {
            return;
        }

        $priceDefinition = new QuantityPriceDefinition(0, new TaxRuleCollection(), 1);
        $price = $this->quantityPriceCalculator->calculate($priceDefinition, $context);

        $promotionItem = $this->builder->buildDeliveryPlaceholderLineItem($discount, $priceDefinition, $price);

        $toCalculate->addLineItems(new LineItemCollection([$promotionItem]));
    }

    private function isAutomaticDisount(LineItem $discountItem): bool
    {
        return empty($discountItem->getPayloadValue('code'));
    }
}
