<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PercentagePriceCalculator
{
    /**
     * @var PriceRoundingInterface
     */
    private $rounding;

    public function __construct(PriceRoundingInterface $rounding)
    {
        $this->rounding = $rounding;
    }

    /**
     * Provide a negative percentage value for discount or a positive percentage value for a surcharge
     *
     * @param float $percentage 10.00 for 10%, -10.0 for -10%
     */
    public function calculate(float $percentage, PriceCollection $prices, SalesChannelContext $context): CalculatedPrice
    {
        $price = $prices->sum();

        $discount = $this->rounding->round(
            $price->getTotalPrice() / 100 * $percentage,
            $context->getContext()->getCurrencyPrecision()
        );

        $taxes = new CalculatedTaxCollection();
        foreach ($prices->getCalculatedTaxes() as $calculatedTax) {
            $tax = $this->rounding->round(
                $calculatedTax->getTax() / 100 * $percentage,
                $context->getContext()->getCurrencyPrecision()
            );

            $price = $this->rounding->round(
                $calculatedTax->getPrice() / 100 * $percentage,
                $context->getContext()->getCurrencyPrecision()
            );

            $taxes->add(
                new CalculatedTax($tax, $calculatedTax->getTaxRate(), $price)
            );
        }

        return new CalculatedPrice($discount, $discount, $taxes, $prices->getTaxRules(), 1);
    }
}
