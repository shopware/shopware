<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupPackagerNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\Exception\LineItemGroupSorterNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountAbsoluteCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountFixedPriceCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountFixedUnitPriceCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Calculator\DiscountPercentageCalculator;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopware\Core\Checkout\Promotion\Exception\SetGroupNotFoundException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Cart Promotion Calculator
 */
class PromotionCalculator
{
    use PromotionCartInformationTrait;

    private AmountCalculator $amountCalculator;

    private AbsolutePriceCalculator $absolutePriceCalculator;

    private LineItemGroupBuilder $groupBuilder;

    private PackageFilter $advancedFilter;

    private AdvancedPackagePicker $advancedPicker;

    private SetGroupScopeFilter $advancedRules;

    private LineItemQuantitySplitter $lineItemQuantitySplitter;

    private DiscountCompositionBuilder $discountCompositionBuilder;

    private PercentagePriceCalculator $percentagePriceCalculator;

    private DiscountPackager $cartScopeDiscountPackager;

    private DiscountPackager $setGroupScopeDiscountPackager;

    private DiscountPackager $setScopeDiscountPackager;

    public function __construct(
        AmountCalculator $amountCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator,
        LineItemGroupBuilder $groupBuilder,
        DiscountCompositionBuilder $compositionBuilder,
        PackageFilter $filter,
        AdvancedPackagePicker $picker,
        SetGroupScopeFilter $advancedRules,
        LineItemQuantitySplitter $lineItemQuantitySplitter,
        PercentagePriceCalculator $percentagePriceCalculator,
        DiscountPackager $cartScopeDiscountPackager,
        DiscountPackager $setGroupScopeDiscountPackager,
        DiscountPackager $setScopeDiscountPackager
    ) {
        $this->amountCalculator = $amountCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->groupBuilder = $groupBuilder;
        $this->discountCompositionBuilder = $compositionBuilder;
        $this->advancedFilter = $filter;
        $this->advancedPicker = $picker;
        $this->advancedRules = $advancedRules;
        $this->lineItemQuantitySplitter = $lineItemQuantitySplitter;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->cartScopeDiscountPackager = $cartScopeDiscountPackager;
        $this->setGroupScopeDiscountPackager = $setGroupScopeDiscountPackager;
        $this->setScopeDiscountPackager = $setScopeDiscountPackager;
    }

    /**
     * Calculates the cart including the new discount line items.
     * The calculation process will first determine the correct values for
     * the different discount line item types (percentage, absolute, ...) and then
     * recalculate the whole cart with these new items.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws InvalidPriceDefinitionException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws PayloadKeyNotFoundException
     */
    public function calculate(LineItemCollection $discountLineItems, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behaviour): void
    {
        // array that holds all excluded promotion ids.
        // if a promotion has exclusions they are added on the stack
        $exclusions = $this->buildExclusions($discountLineItems, $calculated, $context);

        // @todo order $discountLineItems by priority

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
                // hide the notEligibleErrors on automatic discounts
                if (!$this->isAutomaticDisount($discountItem)) {
                    $this->addPromotionNotEligibleError($discountItem->getLabel() ?? $discountItem->getId(), $calculated);
                }

                continue;
            }

            // if promotion is on exclusions stack it is ignored
            if (!$discountItem->hasPayloadValue('promotionId')) {
                continue;
            }

            $promotionId = $discountItem->getPayloadValue('promotionId');

            if (\array_key_exists($promotionId, $exclusions)) {
                $calculated->addErrors(new PromotionExcludedError($discountItem->getDescription() ?? $discountItem->getId()));

                continue;
            }

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
            $calculated->add($discountItem);

            $this->addPromotionAddedNotice($original, $calculated, $discountItem);

            // recalculate for every new discount to get the correct
            // prices for any upcoming iterations
            $this->calculateCart($calculated, $context);
        }
    }

    /**
     * This function builds a complete list of promotions
     * that are excluded somehow.
     * The validation which one to take will be done later.
     */
    private function buildExclusions(LineItemCollection $discountLineItems, Cart $calculated, SalesChannelContext $context): array
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

                if ($discountItem->getPayloadValue('preventCombination')) {
                    $payloadExclusions = [];
                    foreach ($discountLineItems as $exclusionItem) {
                        if (!$exclusionItem->hasPayloadValue('promotionId')) {
                            continue;
                        }

                        $promotionIdToExclude = $exclusionItem->getPayloadValue('promotionId');
                        if ($promotionIdToExclude === $promotionId) {
                            continue;
                        }

                        $payloadExclusions[] = $promotionIdToExclude;
                    }

                    $discountItem->setPayloadValue('exclusions', $payloadExclusions);
                }
            }

            // add all exclusions to the stack
            foreach ($discountItem->getPayloadValue('exclusions') as $id) {
                // check if the promotion is active by its conditions
                if ($this->isRequirementValid($discountItem, $calculated, $context)) {
                    $exclusions[$id] = true;
                }
            }
        }

        return $exclusions;
    }

    /**
     * Calculates and returns the discount based on the settings of
     * the provided discount line item.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws Discount\Filter\Exception\FilterSorterNotFoundException
     * @throws InvalidPriceDefinitionException
     * @throws InvalidScopeDefinitionException
     * @throws InvalidQuantityException
     * @throws LineItemNotFoundException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws LineItemGroupPackagerNotFoundException
     * @throws LineItemGroupSorterNotFoundException
     * @throws SetGroupNotFoundException
     */
    private function calculateDiscount(LineItem $lineItem, Cart $calculatedCart, SalesChannelContext $context): DiscountCalculatorResult
    {
        $discount = new DiscountLineItem(
            $lineItem->getLabel(),
            $lineItem->getPriceDefinition(),
            $lineItem->getPayload(),
            $lineItem->getReferencedId()
        );

        switch ($discount->getScope()) {
            case PromotionDiscountEntity::SCOPE_CART:
                $packager = $this->cartScopeDiscountPackager;

                break;

            case PromotionDiscountEntity::SCOPE_SET:
                $packager = $this->setScopeDiscountPackager;

                break;

            case PromotionDiscountEntity::SCOPE_SETGROUP:
                $packager = $this->setGroupScopeDiscountPackager;

                break;

            default:
                throw new InvalidScopeDefinitionException($discount->getScope());
        }

        $packages = $packager->getMatchingItems($discount, $calculatedCart, $context);

        // check if no result is found,
        // then this would mean -> no discount
        if ($packages->count() <= 0) {
            return new DiscountCalculatorResult(
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
                []
            );
        }

        // remember our initial package count
        $originalPackageCount = $packages->count();

        $packages = $this->enrichPackagesWithCartData($packages, $calculatedCart, $context);

        // every scope packager can have an additional
        // list of rules that can be used to filter out items.
        // thus we enrich our current package with items
        // and run it through the advanced rules if existing
        if ($discount->getScope() !== PromotionDiscountEntity::SCOPE_SETGROUP) {
            $packages = $this->advancedRules->filter($discount, $packages, $context);
        }

        // depending on the selected picker of our
        // discount, the packages might be restructure
        // also make sure we have correct cart items in our restructured packages from the picker
        $packages = $this->advancedPicker->pickItems($discount, $packages);
        $packages = $this->enrichPackagesWithCartData($packages, $calculatedCart, $context);

        // if we have any graduation settings, make sure to reduce the items
        // that are eligible for our discount by executing our graduation resolver.
        $packages = $this->advancedFilter->filterPackages($discount, $packages, $originalPackageCount);
        $packages = $this->enrichPackagesWithCartData($packages, $calculatedCart, $context);

        switch ($discount->getType()) {
            case PromotionDiscountEntity::TYPE_ABSOLUTE:
                $calculator = new DiscountAbsoluteCalculator($this->absolutePriceCalculator);

                break;

            case PromotionDiscountEntity::TYPE_PERCENTAGE:
                $calculator = new DiscountPercentageCalculator($this->absolutePriceCalculator, $this->percentagePriceCalculator);

                break;

            case PromotionDiscountEntity::TYPE_FIXED:
                $calculator = new DiscountFixedPriceCalculator($this->absolutePriceCalculator);

                break;

            case PromotionDiscountEntity::TYPE_FIXED_UNIT:
                $calculator = new DiscountFixedUnitPriceCalculator($this->absolutePriceCalculator);

                break;

            default:
                throw new DiscountCalculatorNotFoundException($discount->getType());
        }

        $result = $calculator->calculate($discount, $packages, $context);

        // now aggregate any composition items
        // which might be duplicated due to separate packages
        $aggregatedCompositionItems = $this->discountCompositionBuilder->aggregateCompositionItems($result->getCompositionItems());
        $result = new DiscountCalculatorResult($result->getPrice(), $aggregatedCompositionItems);

        // get the cart total price => discount may never be higher than this value
        $maxDiscountValue = $this->getMaxDiscountValue($calculatedCart, $context);

        // if our price is larger than the max discount value,
        // then use the max discount value as negative discount
        if (abs($result->getPrice()->getTotalPrice()) > abs($maxDiscountValue)) {
            $result = $this->limitDiscountResult($maxDiscountValue, $packages->getAffectedPrices(), $result, $context);
        }

        return $result;
    }

    /**
     * Calculates a max discount value based on current cart and customer group.
     * If customer is in net customer group, get the cart's net value,
     * otherwise use the gross value as maximum value.
     */
    private function getMaxDiscountValue(Cart $cart, SalesChannelContext $context): float
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_NET) {
            return $cart->getPrice()->getNetPrice();
        }

        return $cart->getPrice()->getTotalPrice();
    }

    /**
     * This function can be used to limit the provided discount data
     * to a maximum threshold value.
     * It will recalculate the price and adjust all discount composition items
     * to match the demanded total price.
     */
    private function limitDiscountResult(float $maxDiscountValue, PriceCollection $priceCollection, DiscountCalculatorResult $originalResult, SalesChannelContext $context): DiscountCalculatorResult
    {
        $price = $this->absolutePriceCalculator->calculate(
            -abs($maxDiscountValue),
            $priceCollection,
            $context
        );

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

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     */
    private function enrichPackagesWithCartData(DiscountPackageCollection $result, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        // set the line item from the cart for each unit
        foreach ($result as $package) {
            $cartItemsForUnit = new LineItemFlatCollection();

            foreach ($package->getMetaData() as $item) {
                /** @var LineItem $cartItem */
                $cartItem = $cart->get($item->getLineItemId());

                // create a new item with only a quantity of x
                // including calculated price for our original cart item
                $qtyItem = $this->lineItemQuantitySplitter->split($cartItem, $item->getQuantity(), $context);

                // add the single item to our unit
                $cartItemsForUnit->add($qtyItem);
            }

            $package->setCartItems($cartItemsForUnit);
        }

        return $result;
    }

    private function isAutomaticDisount(LineItem $discountItem): bool
    {
        return empty($discountItem->getPayloadValue('code'));
    }
}
