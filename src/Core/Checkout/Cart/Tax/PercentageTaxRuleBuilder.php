<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\PercentageTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class PercentageTaxRuleBuilder
{
    public function buildRules(Price $price): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);

        /** @var CalculatedTax $tax */
        foreach ($price->getCalculatedTaxes() as $tax) {
            $rules->add(
                new PercentageTaxRule(
                    $tax->getTaxRate(),
                    $tax->getPrice() / $price->getTotalPrice() * 100
                )
            );
        }

        return $rules;
    }
}
