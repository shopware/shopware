<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PercentageTaxRuleBuilder
{
    public function buildRules(CalculatedPrice $price): TaxRuleCollection
    {
        return $this->buildCollectionRules($price->getCalculatedTaxes(), $price->getTotalPrice());
    }

    public function buildCollectionRules(CalculatedTaxCollection $taxes, float $totalPrice): TaxRuleCollection
    {
        $rules = new TaxRuleCollection([]);

        foreach ($taxes as $tax) {
            $percentage = 0;
            if ($totalPrice > 0) {
                $percentage = $tax->getPrice() / $totalPrice * 100;
            }

            $rules->add(
                new TaxRule(
                    $tax->getTaxRate(),
                    $percentage
                )
            );
        }

        return $rules;
    }
}
