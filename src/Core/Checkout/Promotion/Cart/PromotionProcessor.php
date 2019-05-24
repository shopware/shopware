<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionProcessor implements CartProcessorInterface
{
    public const DATA_KEY = 'promotions';
    public const LINE_ITEM_TYPE = 'promotion';
    public const CART_EXTENSION_KEY = 'cart-promotion-codes';

    /**
     * @var PromotionCalculator
     */
    private $promotionCalculator;

    /**
     * @var PromotionItemBuilder
     */
    private $itemBuilder;

    public function __construct(PromotionCalculator $promotionCalculator, PromotionItemBuilder $itemBuilder)
    {
        $this->promotionCalculator = $promotionCalculator;
        $this->itemBuilder = $itemBuilder;
    }

    /**
     * This function enriches the cart with custom data that has been collected in our previous function.
     * All collected promotions will now be converted into real Promotion Line Items by using our
     * Calculator which validates and fixes our line items and then recalculates the cart after applying promotions.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // if there is no collected promotion we may return - nothing to calculate!
        if (!$data->has(self::DATA_KEY)) {
            return;
        }

        // if we are in recalculation,
        // we must not re-add any promotions. just leave it as it is.
        if ($behavior->isRecalculation()) {
            return;
        }

        $discountLineItems = [];

        // get all promotions that have been collected
        // and prepare them for calculating process
        /** @var PromotionCollection $promotionDefinition */
        $promotionDefinition = $data->get(self::DATA_KEY);

        /** @var PromotionEntity $promotion */
        foreach ($promotionDefinition as $promotion) {
            // lets build separate line items for each
            // of the available discounts within the current promotion
            /** @var array $lineItems */
            $lineItems = $this->buildDiscountLineItems($promotion, $calculated, $context);

            // add to our list of all line items
            // that should be added
            foreach ($lineItems as $nested) {
                $discountLineItems[] = $nested;
            }
        }

        // calculate the whole cart with the
        // new list of created promotion discount line items
        $this->promotionCalculator->calculate(
            new LineItemCollection($discountLineItems),
            $original,
            $calculated,
            $context,
            $behavior
        );
    }

    /**
     * This function builds separate line items for each of the
     * available discounts within the provided promotion.
     * Every item will be built with a corresponding price definition based
     * on the configuration of a discount entity.
     * The resulting list of line items will then be returned and can
     * be added to the cart.
     * The function will already avoid duplicate entries.
     */
    private function buildDiscountLineItems(PromotionEntity $promotion, Cart $cart, SalesChannelContext $context): array
    {
        /** @var PromotionDiscountCollection|null $collection */
        $collection = $promotion->getDiscounts();

        if (!$collection instanceof PromotionDiscountCollection) {
            return [];
        }

        $lineItems = [];

        /** @var PromotionDiscountEntity $discount */
        foreach ($collection->getElements() as $discount) {
            // we only calculate discounts with scope cart in this processor
            if ($discount->getScope() !== PromotionDiscountEntity::SCOPE_CART) {
                continue;
            }
            // skip if already added! we do not update existing items!
            // depending on our recalculation mode, all promotion items have been removed anyway by now.
            // in recalculation mode, we only add NEW items...and not edit existing ones!
            if ($cart->has($discount->getId())) {
                continue;
            }

            /** @var array $itemIds */
            $itemIds = $this->getAllLineItemIds($cart);

            // add a new discount line item for this discount
            // if we have at least one valid item that will be discounted.
            if (count($itemIds) <= 0) {
                continue;
            }

            /* @var LineItem $discountItem */
            $discountItem = $this->itemBuilder->buildDiscountLineItem(
                $promotion,
                $discount,
                $context
            );

            $lineItems[] = $discountItem;
        }

        return $lineItems;
    }

    private function getAllLineItemIds(Cart $cart): array
    {
        return $cart->getLineItems()->fmap(
            static function (LineItem $lineItem) {
                if ($lineItem->getType() === self::LINE_ITEM_TYPE) {
                    return null;
                }

                return $lineItem->getId();
            }
        );
    }
}
