<?php declare(strict_types=1);

namespace Shopware\CartBridge\Order;

use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class MinimumOrderAmountProcessor implements CartProcessorInterface
{
    /**
     * @var AbsolutePriceCalculator
     */
    private $calculator;

    public function __construct(AbsolutePriceCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        StorefrontContext $context
    ): void {
        if (!$context->getCustomer()) {
            return;
        }

        $customerGroup = $context->getCurrentCustomerGroup();
        if (!$customerGroup->getMinimumOrderAmount()) {
            return;
        }

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();
        if ($goods->count() === 0) {
            return;
        }

        $prices = $goods->getPrices();

        if ($customerGroup->getMinimumOrderAmount() <= $prices->sum()->getTotalPrice()) {
            return;
        }

        $surcharge = $this->calculator->calculate(
            $customerGroup->getMinimumOrderAmountSurcharge(),
            $prices,
            $context
        );

        $calculatedCart->getCalculatedLineItems()->add(
            new CalculatedLineItem(
                'minimum-order-value',
                $surcharge,
                1,
                'minimum-order-value',
                'Extra charge for small quantities'
            )
        );
    }
}
