<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Cart;

use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\StructCollection;

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
        CheckoutContext $context
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
