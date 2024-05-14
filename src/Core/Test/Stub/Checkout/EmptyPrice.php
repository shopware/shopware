<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Checkout;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class EmptyPrice extends CalculatedPrice
{
    public function __construct(
        float $unitPrice = 0,
        float $totalPrice = 0,
        ?CalculatedTaxCollection $calculatedTaxes = null,
        ?TaxRuleCollection $taxRules = null,
        int $quantity = 1,
        ?ReferencePrice $referencePrice = null,
        ?ListPrice $listPrice = null,
        ?RegulationPrice $regulationPrice = null
    ) {
        $calculatedTaxes = $calculatedTaxes ?? new CalculatedTaxCollection();
        $taxRules = $taxRules ?? new TaxRuleCollection();

        parent::__construct($unitPrice, $totalPrice, $calculatedTaxes, $taxRules, $quantity, $referencePrice, $listPrice, $regulationPrice);
    }
}
