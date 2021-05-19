<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionProcessor implements CartProcessorInterface
{
    public const DATA_KEY = 'promotions';
    public const LINE_ITEM_TYPE = 'promotion';

    public const SKIP_PROMOTION = 'skipPromotion';

    private PromotionCalculator $promotionCalculator;

    private LineItemGroupBuilder $groupBuilder;

    public function __construct(PromotionCalculator $promotionCalculator, LineItemGroupBuilder $groupBuilder)
    {
        $this->promotionCalculator = $promotionCalculator;
        $this->groupBuilder = $groupBuilder;
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws PayloadKeyNotFoundException
     * @throws InvalidPriceDefinitionException
     */
    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
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
                if (empty($lineItemPromotion->getReferencedId())) {
                    $toCalculate->addErrors(
                        new AutoPromotionNotFoundError($lineItemPromotion->getLabel() ?? $lineItemPromotion->getId())
                    );
                }
            }

            return;
        }

        /** @var LineItemCollection $discountLineItems */
        $discountLineItems = $data->get(self::DATA_KEY);

        // calculate the whole cart with the
        // new list of created promotion discount line items
        $this->promotionCalculator->calculate(
            new LineItemCollection($discountLineItems),
            $original,
            $toCalculate,
            $context,
            $behavior
        );
    }
}
