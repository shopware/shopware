<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionProcessor implements CartProcessorInterface
{
    final public const DATA_KEY = 'promotions';
    final public const LINE_ITEM_TYPE = 'promotion';

    final public const SKIP_PROMOTION = 'skipPromotion';

    /**
     * @internal
     */
    public function __construct(
        private readonly PromotionCalculator $promotionCalculator,
        private readonly LineItemGroupBuilder $groupBuilder
    ) {
    }

    /**
     * @throws CartException
     * @throws PromotionException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        Profiler::trace('cart::promotion::process', function () use ($data, $original, $toCalculate, $context, $behavior): void {
            // always make sure we have
            // the line item group builder for our
            // line item group rule inside the cart data
            $toCalculate->getData()->set(LineItemGroupBuilder::class, $this->groupBuilder);

            // if we are in recalculation,
            // we must not re-add any promotions. just leave it as it is.
            if ($behavior->hasPermission(self::SKIP_PROMOTION)) {
                $items = $original->getLineItems()->filterType(self::LINE_ITEM_TYPE);
                foreach ($items as $item) {
                    $toCalculate->add($item);
                }

                return;
            }

            // if there is no collected promotion we may return - nothing to calculate!
            if (!$data->has(self::DATA_KEY)) {
                $lineItemPromotions = $original->getLineItems()->filterType(self::LINE_ITEM_TYPE);
                foreach ($lineItemPromotions as $lineItemPromotion) {
                    $referencedId = $lineItemPromotion->getReferencedId();
                    if ($referencedId === null || $referencedId === '') {
                        $toCalculate->addErrors(
                            new AutoPromotionNotFoundError($lineItemPromotion->getLabel() ?? $lineItemPromotion->getId())
                        );
                    }
                }

                return;
            }

            /** @var LineItemCollection $discountLineItems */
            $discountLineItems = $data->get(self::DATA_KEY);

            $this->preservePinnedSetPromotions($discountLineItems, $toCalculate, $behavior);

            if ($toCalculate->getPrice()->getTotalPrice() === 0.0) {
                // We'll only display the `PromotionsOnCartPriceZeroError` if a promotion code is input and the cart price is zero. Auto-promotions are not considered in this case.
                $discountPromotionsWithCode = $discountLineItems->filter(static fn (LineItem $lineItem) => !$lineItem->hasPayloadValue('promotionCodeType') || $lineItem->getPayloadValue('promotionCodeType') !== PromotionItemBuilder::PROMOTION_TYPE_GLOBAL);
                if ($discountPromotionsWithCode->count() === 0) {
                    return;
                }

                $toCalculate->addErrors(
                    new PromotionsOnCartPriceZeroError($discountPromotionsWithCode->fmap(static fn (LineItem $lineItem) => $lineItem->getLabel()))
                );

                return;
            }

            // calculate the whole cart with the
            // new list of created promotion discount line items
            $items = new LineItemCollection();
            foreach ($discountLineItems as $lineItem) {
                $lineItem->setShippingCostAware(true);
                $items->add($lineItem);
            }

            $this->promotionCalculator->calculate($items, $original, $toCalculate, $context, $behavior);
        }, 'cart');
    }

    private function preservePinnedSetPromotions(LineItemCollection $discountLineItems, Cart $calculated, CartBehavior $behavior): void
    {
        $pinManual = $behavior->hasPermission(CheckoutPermissions::PIN_MANUAL_PROMOTIONS);
        $pinAutomatic = $behavior->hasPermission(CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS);

        if (!$pinManual && !$pinAutomatic) {
            return;
        }

        foreach ($discountLineItems as $lineItem) {
            $isPinned = $lineItem->getReferencedId() ? $pinManual : $pinAutomatic;

            if (!$isPinned || !$this->hasSerializedSetGroupRules($lineItem)) {
                continue;
            }

            $calculated->add($lineItem);
        }
    }

    private function hasSerializedSetGroupRules(LineItem $lineItem): bool
    {
        if (!$lineItem->hasPayloadValue('discountScope') || !$lineItem->hasPayloadValue('setGroups')) {
            return false;
        }

        $scope = $lineItem->getPayloadValue('discountScope');
        if (!\in_array($scope, [PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::SCOPE_SETGROUP], true)) {
            return false;
        }

        $groups = $lineItem->getPayloadValue('setGroups');
        if (!\is_array($groups)) {
            return false;
        }

        foreach ($groups as $group) {
            if (!\is_array($group)) {
                return true;
            }

            $rules = $group['rules'] ?? null;
            if ($rules !== null && $rules !== [] && !$rules instanceof RuleCollection) {
                return true;
            }
        }

        return false;
    }
}
