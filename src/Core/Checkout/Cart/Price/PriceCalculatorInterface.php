<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\CheckoutContext;

interface PriceCalculatorInterface
{
    public function supports(PriceDefinitionInterface $priceDefinition): bool;

    public function calculate(PriceDefinitionInterface $priceDefinition, PriceCollection $prices, CheckoutContext $context): Price;
}
