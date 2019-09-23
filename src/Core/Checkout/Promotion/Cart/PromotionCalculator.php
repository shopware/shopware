<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountAbsoluteCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountFixedPriceCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountFixedUnitPriceCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountPercentageCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorDefinition;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetGroupScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\SetScopeDiscountPackager;
use Shopware\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Cart Promotion Calculator
 */
class PromotionCalculator
{
    use PromotionCartInformationTrait;

    /**
     * @var AmountCalculator
     */
    private $amountCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    /**
     * @var LineItemGroupBuilder
     */
    private $groupBuilder;

    /**
     * @var DiscountCompositionBuilder
     */
    private $discountCompositionBuilder;

    public function __construct(AmountCalculator $amountCalculator, AbsolutePriceCalculator $absolutePriceCalculator, QuantityPriceCalculator $quantityPriceCalculator, LineItemGroupBuilder $groupBuilder, DiscountCompositionBuilder $compositionBuilder)
    {
        $this->amountCalculator = $amountCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->groupBuilder = $groupBuilder;
        $this->discountCompositionBuilder = $compositionBuilder;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    /**
     * Calculates the cart including the new discount line items.
     * The calculation process will first determine the correct values for
     * the different discount line item types (percentage, absolute, ...) and then
     * recalculate the whole cart with these new items.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws InvalidPriceDefinitionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
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
            // if we dont have a scope
            // then skip it, it might not belong to us
            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            // deliveries have their own processor and calculator
            if ($discountItem->getPayloadValue('discountScope') === PromotionDiscountEntity::SCOPE_DELIVERY) {
                continue;
            }

            // we have to verify if the line item is still valid
            // depending on the added requirements and conditions.
            if (!$this->isRequirementValid($discountItem, $calculated, $context)) {
                $this->addDeleteNoticeToCart($original, $calculated, $discountItem);
                continue;
            }

            /** @var DiscountCalculatorResult $result */
            $result = $this->calculateDiscount($discountItem, $calculated, $context);

            // if our price is 0,00 because of whatever reason, make sure to skip it.
            // this can be if the price-definition filter is none,
            // or if a fixed price is set to the price of the product itself.
            if (abs($result->getPrice()->getTotalPrice()) === 0.0) {
                continue;
            }

            // use our calculated price
            $discountItem->setPrice($result->getPrice());

            // also add our discounted items and their meta data
            // to our discount line item payload
            $discountItem->setPayloadValue(
                'composition',
                $this->discountCompositionBuilder->buildCompositionPayload($result->getCompositionItems())
            );

            // add our discount item to the cart
            $calculated->addLineItems(new LineItemCollection([$discountItem]));

            $this->addAddedNoticeToCart($original, $calculated, $discountItem);

            // recalculate for every new discount to get the correct
            // prices for any upcoming iterations
            $this->calculateCart($calculated, $context);
        }
    }

    /**
     * Calculates and returns the discount based on the settings of
     * the provided discount line item.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws InvalidPriceDefinitionException
     * @throws InvalidScopeDefinitionException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     */
    private function calculateDiscount(LineItem $discountItem, Cart $calculatedCart, SalesChannelContext $context): DiscountCalculatorResult
    {
        // get the cart total price => discount may never be higher than this value
        /** @var float $maxDiscountValue */
        $maxDiscountValue = $calculatedCart->getPrice()->getTotalPrice();

        /** @var string $scope */
        $scope = $discountItem->getPayloadValue('discountScope');

        /** @var string $type */
        $type = $discountItem->getPayloadValue('discountType');

        /** @var DiscountPackagerInterface $packager */
        $packager = null;

        switch ($scope) {
            case PromotionDiscountEntity::SCOPE_CART:
                $packager = new CartScopeDiscountPackager();
                break;

            case PromotionDiscountEntity::SCOPE_SET:
                $packager = new SetScopeDiscountPackager($this->groupBuilder);
                break;

            case PromotionDiscountEntity::SCOPE_SETGROUP:
                $packager = new SetGroupScopeDiscountPackager($this->groupBuilder);
                break;

            default:
                throw new InvalidScopeDefinitionException($scope);
                break;
        }

        /** @var LineItemQuantity[] $itemsToReduce */
        $itemsToReduce = $packager->getMatchingItems($discountItem, $calculatedCart, $context);

        // check if no matching items exist,
        // then this would mean -> no discount
        if (count($itemsToReduce) <= 0) {
            return new DiscountCalculatorResult(
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
                []
            );
        }

        $discountDefinition = new DiscountCalculatorDefinition(
            $discountItem->getLabel(),
            $discountItem->getPriceDefinition(),
            $discountItem->getPayload(),
            $discountItem->getReferencedId(),
            $itemsToReduce
        );

        /** @var DiscountCalculatorInterface $calculator */
        $calculator = null;

        switch ($type) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                $calculator = new DiscountAbsoluteCalculator($this->absolutePriceCalculator);
                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                $calculator = new DiscountPercentageCalculator($this->absolutePriceCalculator);
                break;

            case PromotionDiscountEntity::TYPE_FIXED:
                $calculator = new DiscountFixedPriceCalculator($this->absolutePriceCalculator);
                break;

            case PromotionDiscountEntity::TYPE_FIXED_UNIT:
                $calculator = new DiscountFixedUnitPriceCalculator($this->absolutePriceCalculator);
                break;

            default:
                throw new DiscountCalculatorNotFoundException($type);
                break;
        }

        $eligibleItems = $this->getEligibleItems($discountDefinition, $calculatedCart->getLineItems());

        $targetPrices = $this->getTargetPrices($discountDefinition, $eligibleItems, $context);

        /** @var DiscountCalculatorResult $result */
        $result = $calculator->calculate($discountDefinition, $targetPrices, $eligibleItems, $context);

        // if our price is larger than the max discount value,
        // then use the max discount value as negative discount
        if (abs($result->getPrice()->getTotalPrice()) > abs($maxDiscountValue)) {
            $result = $this->limitDiscountResult($maxDiscountValue, $targetPrices, $result, $context);
        }

        return $result;
    }

    /**
     * This function can be used to limit the provided discount data
     * to a maximum threshold value.
     * It will recalculate the price and adjust all discount composition items
     * to match the demanded total price.
     */
    private function limitDiscountResult(float $maxDiscountValue, PriceCollection $priceCollection, DiscountCalculatorResult $originalResult, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var CalculatedPrice $price */
        $price = $this->absolutePriceCalculator->calculate(
            -abs($maxDiscountValue),
            $priceCollection,
            $context
        );

        /** @var array $adjustedItems */
        $adjustedItems = $this->discountCompositionBuilder->adjustCompositionItemValues($price, $originalResult->getCompositionItems());

        // update our result price to the new one
        return new DiscountCalculatorResult($price, $adjustedItems);
    }

    /**
     * Validates the included requirements and returns if the
     * line item is allowed to be added to the actual cart.
     */
    private function isRequirementValid(LineItem $lineItem, Cart $calculated, SalesChannelContext $context): bool
    {
        // if we dont have any requirement, then it's obviously valid
        if (!$lineItem->getRequirement()) {
            return true;
        }

        $scopeWithoutLineItem = new CartRuleScope($calculated, $context);

        // set our currently registered group builder in our cart data
        // to be able to use that one within our line item rule
        $data = $scopeWithoutLineItem->getCart()->getData();
        $data->set(LineItemGroupBuilder::class, $this->groupBuilder);

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

    private function getEligibleItems(DiscountCalculatorDefinition $discount, LineItemCollection $items): LineItemCollection
    {
        $result = new LineItemCollection();

        /** @var LineItem $lineItem */
        foreach ($items->getFlat() as $lineItem) {
            $id = $lineItem->getId();

            if ($discount->hasItem($id)) {
                $result->add($lineItem);
            }
        }

        return $result;
    }

    private function getTargetPrices(DiscountCalculatorDefinition $discount, LineItemCollection $targetItems, SalesChannelContext $context): PriceCollection
    {
        $affectedPrices = new PriceCollection();

        /** @var LineItem $lineItem */
        foreach ($targetItems->getFlat() as $lineItem) {
            if ($discount->hasItem($lineItem->getId())) {
                /** @var int $quantity */
                $quantity = $discount->getItem($lineItem->getId())->getQuantity();

                /** @var QuantityPriceDefinition $definition */
                $definition = $lineItem->getPriceDefinition();
                $definition->setQuantity($quantity);

                $quantityPrice = $this->quantityPriceCalculator->calculate($definition, $context);
                $affectedPrices->add($quantityPrice);
            }
        }

        return $affectedPrices;
    }
}
