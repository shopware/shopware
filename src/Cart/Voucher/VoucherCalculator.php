<?php

namespace Shopware\Cart\Voucher;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Error\VoucherModeNotFoundError;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Context\Struct\ShopContext;

class VoucherCalculator
{
    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var PriceCalculator
     */
    private $priceCalculator;

    /**
     * @var PercentageTaxRuleBuilder
     */
    private $percentageTaxRuleBuilder;

    public function __construct(
        PercentagePriceCalculator $percentagePriceCalculator,
        PriceCalculator $priceCalculator,
        PercentageTaxRuleBuilder $percentageTaxRuleBuilder
    ) {
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->priceCalculator = $priceCalculator;
        $this->percentageTaxRuleBuilder = $percentageTaxRuleBuilder;
    }

    public function calculate(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        VoucherData $voucher,
        LineItemInterface $lineItem
    ): CalculatedVoucher {

        $prices = $calculatedCart->getCalculatedLineItems()->filterGoods()->getPrices();

        if ($voucher->getPercentage() !== null) {
            /** @var PercentageVoucherData $voucher */
            $discount = $this->percentagePriceCalculator->calculate(
                abs($voucher->getPercentage()) * -1,
                $prices,
                $context
            );

        } else {
            $price = $voucher->getAbsolute();

            /** @var VoucherData $voucher */
            $discount = $this->priceCalculator->calculate(
                new PriceDefinition(
                    $price->getPrice(),
                    $this->percentageTaxRuleBuilder->buildRules(
                        $prices->sum()
                    ),
                    1,
                    $price->isCalculated()
                ),
                $context
            );
        }

        return new CalculatedVoucher($lineItem->getIdentifier(), $lineItem, $discount, $voucher->getRule());
    }
}