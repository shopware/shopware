<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\TaxProvider;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 * This is an extension to the common TaxCalculator
 * It is used during recalculation of carts, when taxes are given by tax providers,
 * where we do not want to recalculate the taxes, but just use the given ones
 * We shall not recalculate the taxes when in TAX_STATE_GROSS, as we simply have to add the provided taxes
 */
#[Package('checkout')]
class TaxAdjustmentCalculator extends TaxCalculator
{
    public function calculateGrossTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        $taxes = [];
        foreach ($rules as $rule) {
            $taxes[] = $this->calculateTaxFromGrossPrice($price, $rule);
        }

        return new CalculatedTaxCollection($taxes);
    }

    private function calculateTaxFromGrossPrice(float $gross, TaxRule $rule): CalculatedTax
    {
        return $this->calculateTaxFromNetPrice($gross, $rule);
    }
}
