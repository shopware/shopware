<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
trait PromotionExclusionTrait
{
    abstract private function isRequirementValid(LineItem $lineItem, Cart $calculated, SalesChannelContext $context): bool;

    /**
     * Collects the excluded promotion ids per promotion and converts the preventCombination setting
     * into explicit exclusions, both on the preventing promotion and on all others.
     *
     * @return array<string, list<string>>
     */
    private function buildExclusions(LineItemCollection $discountLineItems): array
    {
        $exclusions = [];
        $preventCombinationIds = [];

        foreach ($discountLineItems as $discountItem) {
            $promotionId = $discountItem->getPayloadValue('promotionId');
            if (!\is_string($promotionId) || !$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            $configuredExclusions = $discountItem->getPayloadValue('exclusions');
            $exclusions[$promotionId] = \is_array($configuredExclusions) ? array_values($configuredExclusions) : [];

            if ($discountItem->getPayloadValue('preventCombination')) {
                $preventCombinationIds[] = $promotionId;
            }
        }

        if ($preventCombinationIds === []) {
            return $exclusions;
        }

        $allPromotionIds = array_keys($exclusions);

        foreach ($exclusions as $promotionId => $configuredExclusions) {
            $merged = \in_array($promotionId, $preventCombinationIds, true)
                ? $allPromotionIds
                : [...$configuredExclusions, ...$preventCombinationIds];

            $exclusions[$promotionId] = array_values(array_unique(array_diff($merged, [$promotionId])));
        }

        return $exclusions;
    }

    /**
     * Checks if a discount item is excluded by an applied promotion of higher priority.
     *
     * @param array<string, list<string>> $exclusions see buildExclusions()
     * @param array<string, bool> $applied whether the discount made it into the cart, per discount line item id, recorded for every item the calculator has decided on
     */
    private function isExcluded(LineItem $checkedItem, LineItemCollection $sortedDiscountItems, array $exclusions, array $applied, Cart $calculated, SalesChannelContext $context): bool
    {
        $excludedPromotionIds = [];
        $checkedPromotionId = $checkedItem->getPayloadValue('promotionId');
        $lineItems = $calculated->getLineItems();

        foreach ($sortedDiscountItems as $discountItem) {
            // if we dont have a scope: skip it, it might not belong to us
            if (!$discountItem->hasPayloadValue('discountScope')) {
                continue;
            }

            if ($discountItem->getPayloadValue('priority') < $checkedItem->getPayloadValue('priority')) {
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
            if (isset($excludedPromotionIds[$promotionId])) {
                continue;
            }

            $isApplied = $applied[$discountItem->getId()]
                ?? ($lineItems->exists($discountItem) || $this->isRequirementValid($discountItem, $calculated, $context));

            if (!$isApplied) {
                continue;
            }

            foreach ($exclusions[$promotionId] ?? [] as $id) {
                if ($id === $checkedPromotionId) {
                    return true;
                }

                $excludedPromotionIds[$id] = true;
            }
        }

        return false;
    }
}
