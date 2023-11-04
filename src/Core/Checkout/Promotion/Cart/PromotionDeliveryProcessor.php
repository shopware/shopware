<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class PromotionDeliveryProcessor implements CartProcessorInterface
{
    final public const SKIP_DELIVERY_RECALCULATION = 'skipDeliveryRecalculation';

    /**
     * @internal
     */
    public function __construct(
        private readonly PromotionDeliveryCalculator $calculator,
        private readonly LineItemGroupBuilder $groupBuilder
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        // always make sure we have
        // the line item group builder for our
        // line item group rule inside the cart data
        $toCalculate->getData()->set(LineItemGroupBuilder::class, $this->groupBuilder);

        // if there is no collected promotion we may return - nothing to calculate!
        if (!$data->has(PromotionProcessor::DATA_KEY)) {
            return;
        }

        // if we are in recalculation,
        // we must not re-add any promotions. just leave it as it is.
        if ($behavior->hasPermission(self::SKIP_DELIVERY_RECALCULATION)) {
            return;
        }

        /** @var LineItemCollection $discountLineItems */
        $discountLineItems = $data->get(PromotionProcessor::DATA_KEY);

        // calculate the whole cart with the
        // new list of created promotion discount line items
        $this->calculator->calculate(
            new LineItemCollection($discountLineItems),
            $original,
            $toCalculate,
            $context
        );
    }
}
