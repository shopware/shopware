<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PercentageTaxRuleBuilder
{
    public function buildRules(CalculatedPrice $price): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);

        /** @var CalculatedTax $tax */
        foreach ($price->getCalculatedTaxes() as $tax) {
            $rules->add(
                new TaxRule(
                    $tax->getTaxRate(),
                    $tax->getPrice() / $price->getTotalPrice() * 100
                )
            );
        }

        return $rules;
    }
}
