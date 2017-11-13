<?php

namespace Shopware\CartBridge\Payment;


use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\LineItem\Discount;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Cart\LineItem\CalculatedLineItem;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;

class PaymentSurchargeProcessor implements CartProcessorInterface
{

    /** @var  PercentageTaxRuleBuilder */
    private $percentageTaxRuleBuilder;

    /** @var  PercentagePriceCalculator */
    private $percentagePriceCalculator;

    /** @var  PriceCalculator */
    private $priceCalculator;

    /**
     * PaymentSurchargeProcessor constructor.
     */
    public function __construct(
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder,
        PercentagePriceCalculator $percentagePriceCalculator,
        PriceCalculator $priceCalculator)
    {
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->priceCalculator = $priceCalculator;
    }

    public function process(
        CartContainer $cartContainer,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void
    {
        if (!$context->getCustomer()) {
            return;
        }

        $payment = $context->getPaymentMethod();

        $goods = $calculatedCart->getCalculatedLineItems()->filterGoods();

        if ($goods->count() === 0) {
            return;
        }

        switch (true) {
            case $payment->getAbsoluteSurcharge() !== null:
                $rules = $this->percentageTaxRuleBuilder->buildRules(
                    $goods->getPrices()->sum()
                );
                $surcharge = $this->priceCalculator->calculate(
                    new PriceDefinition($payment->getAbsoluteSurcharge(), $rules, 1, true),
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

        $calculatedCart->getCalculatedLineItems()->add(
            new CalculatedLineItem('payment', $surcharge, 1, 'surcharge')
        );
    }
}