<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionProcessor implements CartProcessorInterface
{
    public const DATA_KEY = 'promotions';
    public const LINE_ITEM_TYPE = 'promotion';

    public const SKIP_PROMOTION = 'skipPromotion';

    /**
     * @var PromotionCalculator
     */
    private $promotionCalculator;

    /**
     * @var LineItemGroupBuilder
     */
    private $groupBuilder;

    public function __construct(PromotionCalculator $promotionCalculator, LineItemGroupBuilder $groupBuilder)
    {
        $this->promotionCalculator = $promotionCalculator;
        $this->groupBuilder = $groupBuilder;
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $calculated, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // always make sure we have
        // the line item group builder for our
        // line item group rule inside the cart data
        $calculated->getData()->set(LineItemGroupBuilder::class, $this->groupBuilder);

        // if there is no collected promotion we may return - nothing to calculate!
        if (!$data->has(self::DATA_KEY)) {
            if (Feature::isActive('FEATURE_NEXT_10058')) {
                $lineItemPromotions = $original->getLineItems()->filterType(self::LINE_ITEM_TYPE);
                foreach ($lineItemPromotions as $lineItemPromotion) {
                    if (empty($lineItemPromotion->getReferencedId())) {
                        $calculated->addErrors(
                            new AutoPromotionNotFoundError($lineItemPromotion->getLabel())
                        );
                    }
                }
            }

            return;
        }

        // if we are in recalculation,
        // we must not re-add any promotions. just leave it as it is.
        if ($behavior->hasPermission(self::SKIP_PROMOTION)) {
            return;
        }

        /** @var LineItemCollection $discountLineItems */
        $discountLineItems = $data->get(self::DATA_KEY);

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
}
