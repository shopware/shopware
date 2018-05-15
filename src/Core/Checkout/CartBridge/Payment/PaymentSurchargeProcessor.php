<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Payment;

use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class PaymentSurchargeProcessor implements CartProcessorInterface
{
    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    public function __construct(
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator
    ) {
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
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

        $payment = $context->getPaymentMethod();

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();

        if ($goods->count() === 0) {
            return;
        }

        // todo@dr implement validation for payment method (use validator)

        switch (true) {
            case $payment->getAbsoluteSurcharge() !== null:
                $surcharge = $this->absolutePriceCalculator->calculate(
                    $payment->getAbsoluteSurcharge(),
                    $goods->getPrices(),
                    $context
                );

                break;
            case $payment->getPercentageSurcharge() !== null:
                $surcharge = $this->percentagePriceCalculator->calculate(
                    $payment->getPercentageSurcharge(),
                    $goods->getPrices(),
                    $context
                );

                break;
            default:
                return;
        }

        $calculatedLineItem = new CalculatedLineItem(
            'payment',
            $surcharge,
            1,
            'surcharge',
            'Payment surcharge'
        );

        $calculatedCart->getCalculatedLineItems()->add($calculatedLineItem);
    }
}
