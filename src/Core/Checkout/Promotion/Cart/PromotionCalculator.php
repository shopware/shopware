<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
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
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountUnknownConditionError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Shopware\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Cart Promotion Calculator
 */
#[Package('checkout')]
class PromotionCalculator
{
    use PromotionCartInformationTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly AmountCalculator $amountCalculator,
        private readonly AbsolutePriceCalculator $absolutePriceCalculator,
        private readonly LineItemGroupBuilder $groupBuilder,
        private readonly DiscountCompositionBuilder $discountCompositionBuilder,
        private readonly PackageFilter $advancedFilter,
        private readonly AdvancedPackagePicker $advancedPicker,
        private readonly SetGroupScopeFilter $advancedRules,
        private readonly LineItemQuantitySplitter $lineItemQuantitySplitter,
        private readonly PercentagePriceCalculator $percentagePriceCalculator,
        private readonly DiscountPackager $cartScopeDiscountPackager,
        private readonly DiscountPackager $setGroupScopeDiscountPackager,
        private readonly DiscountPackager $setScopeDiscountPackager
    ) {
    }

    /**
     * Calculates the cart including the new discount line items.
     * The calculation process will first determine the correct values for
     * the different discount line item types (percentage, absolute, ...) and then
     * recalculate the whole cart with these new items.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws CartException
     */
    public function calculate(LineItemCollection $discountLineItems, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behaviour): void
    {
        // sort discount line items by priority before building exclusions and calculating discounts
        $discountLineItems->sort(static function (LineItem $a, LineItem $b) {
            return $b->getPayloadValue('priority') <=> $a->getPayloadValue('priority');
        });

        $this->buildExclusionPayload($discountLineItems);

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

            // Pinned set promotions restored from an order have already been copied with their historical price.
            if ($calculated->has($discountItem->getId())) {
                continue;
            }

            $isAutomaticDiscount = $this->isAutomaticDiscount($discountItem);

            // we have to verify if the line item is still valid
            // depending on the added requirements and conditions.
            if (!$this->isRequirementValid($discountItem, $calculated, $context)) {
                // hide the notEligibleErrors on automatic discounts
                if (!$isAutomaticDiscount) {
                    $name = $discountItem->getLabel() ?? $discountItem->getId();
                    if ($context->getCustomer() === null && $discountItem->getPayloadValue('hasPersonaRestriction')) {
                        $calculated->addErrors(new PromotionNotEligibleError($name, 'not-logged-in'));
                    } else {
                        $ruleIds = \is_array($discountItem->getPayloadValue('conditionRuleIds'))
                            ? array_values($discountItem->getPayloadValue('conditionRuleIds'))
                            : [];
                        $calculated->addErrors(new PromotionNotEligibleError($name, null, $ruleIds));
                    }
                }

                continue;
            }

            if (!$discountItem->hasPayloadValue('promotionId')) {
                continue;
            }

            // if promotion is on exclusions stack it is ignored
            if ($this->isExcluded($discountItem, $discountLineItems, $calculated, $context)) {
                if (!$isAutomaticDiscount) {
                    $calculated->addErrors(new PromotionExcludedError($discountItem->getDescription() ?? $discountItem->getId()));
                }

                continue;
            }

            $result = $this->calculateDiscount($discountItem, $calculated, $context);

            // if our price is 0,00 because of whatever reason, make sure to skip it.
            // this can be if the price-definition filter is none,
            // or if a fixed price is set to the price of the product itself.
            if (abs($result->getPrice()->getTotalPrice()) === 0.0) {
                // if the zero result is caused by a filter condition that is no longer registered
                // (e.g. the extension providing it was uninstalled), the discount would vanish
                // silently - add a warning so the removal is visible to the user
                $unknownCondition = $this->getUnknownCondition($discountItem->getPriceDefinition());
                if ($unknownCondition !== null) {
                    $calculated->addErrors(new PromotionDiscountUnknownConditionError($discountItem, $unknownCondition->getOriginalName()));
                }

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
     * Converts preventCombination setting into explicit exclusions to be checked later on
     */
    private function buildExclusionPayload(LineItemCollection $discountLineItems): void
    {
        // collect all preventCombination promotions and prepare an ID list of all promotions currently considered
        $preventCombinationPromotionIdMapping = [];
        $allPromotionIdMapping = [];

        foreach ($discountLineItems as $discountItem) {
            $currentPromotionId = $discountItem->getPayloadValue('promotionId');
            if ($currentPromotionId === null) {
                continue;
            }

            $allPromotionIdMapping[$currentPromotionId] = true;

            if ($discountItem->getPayloadValue('preventCombination')) {
                $preventCombinationPromotionIdMapping[$currentPromotionId] = true;
            }
        }

        if (empty($preventCombinationPromotionIdMapping)) {
            return;
        }

        $preventCombinationPromotionIds = \array_keys($preventCombinationPromotionIdMapping);
        $allPromotionIds = \array_keys($allPromotionIdMapping);

        // add explicit exclusions both to the excluding and excluded items
        foreach ($discountLineItems as $discountItem) {
            $currentPromotionId = $discountItem->getPayloadValue('promotionId');
            if ($currentPromotionId === null) {
                continue;
            }

            if ($discountItem->getPayloadValue('preventCombination')) {
                // if preventCombination is set, no explicit exclusions exist yet. Add exclusions for all other promotions.
                $newExclusions = $allPromotionIds;
            } else {
                // if preventCombination is not set, add exclusions for all promotions set to "prevent combination" to the ones already set.
                $originalExclusions = $discountItem->getPayloadValue('exclusions');
                $newExclusions = \array_unique(\array_merge($originalExclusions, $preventCombinationPromotionIds));
            }
            $filteredExclusions = \array_filter($newExclusions, fn($excludedPromotionId) => $excludedPromotionId !== $currentPromotionId);
            $discountItem->setPayloadValue('exclusions', $filteredExclusions);
        }
    }

    /**
     * Checks if a discount item is excluded by another promotion of higher priority.
     */
    private function isExcluded(LineItem $checkedItem, LineItemCollection $sortedDiscountItems, Cart $calculated, SalesChannelContext $context): bool
    {
        $exclusions = [];
        $checkedPromotionId = $checkedItem->getPayloadValue('promotionId');
        $lineItems = $calculated->getLineItems();

        foreach ($sortedDiscountItems as $discountItem) {
            // if we dont have a scope: skip it, it might not belong to us
            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            $priorityDiff = $discountItem->getPayloadValue('priority') - $checkedItem->getPayloadValue('priority');

            if ($priorityDiff < 0) {
                // collection is sorted by priority, from here on out there are only lower-priority items
                break;
            }

            $promotionId = $discountItem->getPayloadValue('promotionId');
            if ($promotionId === null) {
                // malformed discountItems without promotionId shouldn't be able to exclude anything
                continue;
            }

            if ($promotionId === $checkedPromotionId) {
                // within the same priority, enforce the loading order: whichever item is loaded first enforces its exclusions and can't be excluded by later items.
                // if we'd continue instead, two same-priority promotions could exclude each other and neither would be added.
                break;
            }

            // if promotion is on exclusions stack it is ignored
            // this avoids cycles that both promotions exclude each other
            if (isset($exclusions[$promotionId])) {
                continue;
            }

            if (!$lineItems->exists($discountItem) && !$this->isRequirementValid($discountItem, $calculated, $context)) {
                // $discountItem's requirements are not fulfilled (doesn't need to be checked if it was already added)
                continue;
            }

            foreach ($discountItem->getPayloadValue('exclusions') as $id) {
                if ($id === $checkedPromotionId) {
                    return true;
                }
                $exclusions[$id] = true;
            }
        }

        return false;
    }

    /**
     * Calculates and returns the discount based on the settings of
     * the provided discount line item.
     *
     * @throws DiscountCalculatorNotFoundException
     * @throws PromotionException
     * @throws InvalidScopeDefinitionException
     * @throws CartException
     */
    private function calculateDiscount(LineItem $item, Cart $calculatedCart, SalesChannelContext $context): DiscountCalculatorResult
    {
        /** @var string $label */
        $label = $item->getLabel();

        /** @var PriceDefinitionInterface $priceDefinition */
        $priceDefinition = $item->getPriceDefinition();

        $discount = new DiscountLineItem(
            $label,
            $priceDefinition,
            $item->getPayload(),
            $item->getReferencedId()
        );

        $packager = match ($discount->getScope()) {
            PromotionDiscountEntity::SCOPE_CART => $this->cartScopeDiscountPackager,
            PromotionDiscountEntity::SCOPE_SET => $this->setScopeDiscountPackager,
            PromotionDiscountEntity::SCOPE_SETGROUP => $this->setGroupScopeDiscountPackager,
            default => throw PromotionException::invalidScopeDefinition($discount->getScope()),
        };

        $packages = $packager->getMatchingItems($discount, $calculatedCart, $context);

        // check if no result is found,
        // then this would mean -> no discount
        if ($packages->count() <= 0) {
            if (!$this->isAutomaticDiscount($item) && $this->isRestrictedToMissingProducts($discount, $calculatedCart, $context)) {
                $calculatedCart->addErrors(new PromotionNotEligibleError($discount->getLabel(), 'specific-products'));
            }

            return new DiscountCalculatorResult(
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
                []
            );
        }

        // remember our initial package count
        $originalPackageCount = $packages->count();

        $shouldSplit = $discount->getScope() !== PromotionDiscountEntity::SCOPE_CART || $discount->isProductRestricted();
        if (!Feature::isActive('PERFORMANCE_TWEAKS')) {
            $shouldSplit = true;
        }

        $splitItems = [];
        foreach ($calculatedCart->getLineItems() as $split) {
            $isStackable = $split->isStackable();
            $split->setStackable(true);
            $splitItems[$split->getId()] = $this->lineItemQuantitySplitter->split($split, $shouldSplit ? 1 : $split->getQuantity(), $context);
            $split->setStackable($isStackable);
        }

        $packages = $this->enrichPackagesWithCartData($packages, $splitItems);

        // every scope packager can have an additional
        // list of rules that can be used to filter out items.
        // thus we enrich our current package with items
        // and run it through the advanced rules if existing
        if ($discount->getScope() !== PromotionDiscountEntity::SCOPE_SETGROUP) {
            $packages = $this->advancedRules->filter($discount, $packages, $context);

            if ($packages->count() === 0 && !$this->isAutomaticDiscount($item) && $this->isRestrictedToMissingProducts($discount, $calculatedCart, $context)) {
                $calculatedCart->addErrors(new PromotionNotEligibleError($discount->getLabel(), 'specific-products'));

                return new DiscountCalculatorResult(
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
                    []
                );
            }
        }

        // depending on the selected picker of our
        // discount, the packages might be restructured
        // also make sure we have correct cart items in our restructured packages from the picker
        $packages = $this->advancedPicker->pickItems($discount, $packages);
        $packages = $this->enrichPackagesWithCartData($packages, $splitItems);

        // if we have any graduation settings, make sure to reduce the items
        // that are eligible for our discount by executing our graduation resolver.
        $packages = $this->advancedFilter->filterPackages($discount, $packages, $originalPackageCount);
        $packages = $this->enrichPackagesWithCartData($packages, $splitItems);

        $calculator = match ($discount->getType()) {
            PromotionDiscountEntity::TYPE_ABSOLUTE => new DiscountAbsoluteCalculator($this->absolutePriceCalculator),
            PromotionDiscountEntity::TYPE_PERCENTAGE => new DiscountPercentageCalculator($this->absolutePriceCalculator, $this->percentagePriceCalculator),
            PromotionDiscountEntity::TYPE_FIXED => new DiscountFixedPriceCalculator($this->absolutePriceCalculator),
            PromotionDiscountEntity::TYPE_FIXED_UNIT => new DiscountFixedUnitPriceCalculator($this->absolutePriceCalculator),
            default => throw PromotionException::discountCalculatorNotFound($discount->getType()),
        };

        $result = $calculator->calculate($discount, $packages, $context);

        if ($discount->getType() === PromotionDiscountEntity::TYPE_FIXED_UNIT && $result->getCompositionItems() === []) {
            $calculatedCart->addErrors(new PromotionNotEligibleError($discount->getLabel()));
        }

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
        // if we don't have any requirement, then it's obviously valid
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
     * @param array<string, LineItem> $splitItems
     *
     * @throws CartException
     */
    private function enrichPackagesWithCartData(DiscountPackageCollection $result, array $splitItems): DiscountPackageCollection
    {
        $validPackages = [];

        foreach ($result as $package) {
            $cartItems = $package->getCartItems()->getElements();

            foreach ($package->getMetaData() as $key => $item) {
                if (\array_key_exists($key, $cartItems)) {
                    continue;
                }

                $lineItemId = $item->getLineItemId();

                if (!\array_key_exists($lineItemId, $splitItems)) {
                    continue 2;
                }

                $cartItems[$key] = $splitItems[$lineItemId];
            }

            // assign instead of add for performance reasons
            $package->getCartItems()->assign(['elements' => $cartItems]);
            $validPackages[] = $package;
        }

        return new DiscountPackageCollection($validPackages);
    }

    private function isRestrictedToMissingProducts(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): bool
    {
        if (!$discount->isConsiderAdvancedRules()) {
            return false;
        }

        $priceDefinition = $discount->getPriceDefinition();
        $filter = $priceDefinition instanceof FilterableInterface ? $priceDefinition->getFilter() : null;

        if ($filter === null) {
            return false;
        }

        $products = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        if ($products->count() === 0) {
            return false;
        }

        foreach ($products as $product) {
            if ($filter->match(new LineItemScope($product, $context))) {
                return false;
            }
        }

        return true;
    }

    private function isAutomaticDiscount(LineItem $discountItem): bool
    {
        $code = $discountItem->getPayloadValue('code');

        return $code === null || $code === '';
    }

    /**
     * Returns the first unknown (no longer registered) condition inside the price definition's
     * filter, or null if every condition is resolvable. Containers are searched recursively,
     * because unknown conditions are substituted at leaf level on decode.
     */
    private function getUnknownCondition(?PriceDefinitionInterface $priceDefinition): ?UnknownConditionRule
    {
        if (!$priceDefinition instanceof FilterableInterface) {
            return null;
        }

        return $this->findUnknownCondition($priceDefinition->getFilter());
    }

    private function findUnknownCondition(?Rule $rule): ?UnknownConditionRule
    {
        if ($rule instanceof UnknownConditionRule) {
            return $rule;
        }

        if ($rule instanceof Container) {
            foreach ($rule->getRules() as $nested) {
                $unknownCondition = $this->findUnknownCondition($nested);
                if ($unknownCondition !== null) {
                    return $unknownCondition;
                }
            }
        }

        return null;
    }
}
