<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;

interface StorefrontProductBasicInterface
{
    public function isAvailable(): bool;

    public function getCalculatedListingPrice(): CalculatedPrice;

    public function setCalculatedListingPrice(CalculatedPrice $calculatedListingPrice): void;

    public function setCalculatedContextPrices(CalculatedPriceCollection $prices): void;

    public function getCalculatedContextPrices(): CalculatedPriceCollection;

    public function getCalculatedPrice(): CalculatedPrice;

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void;

    public function getListingPriceDefinition(\Shopware\Core\Framework\Context $context): PriceDefinition;

    public function getContextPriceDefinitions(\Shopware\Core\Framework\Context $context): PriceDefinitionCollection;

    public function getPriceDefinition(Context $context): PriceDefinition;
}
