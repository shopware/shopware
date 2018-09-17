<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\CheckoutContext;

interface TaxAmountCalculatorInterface
{
    public function calculate(PriceCollection $priceCollection, CheckoutContext $context): CalculatedTaxCollection;
}
