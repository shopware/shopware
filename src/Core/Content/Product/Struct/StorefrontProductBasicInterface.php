<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Struct;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Framework\Context;

interface StorefrontProductBasicInterface
{
    public function isAvailable(): bool;

    public function getCalculatedListingPrice(): CalculatedPrice;

    public function setCalculatedListingPrice(CalculatedPrice $calculatedListingPrice): void;

    public function setCalculatedPriceRules(CalculatedPriceCollection $prices): void;

    public function getCalculatedPriceRules(): CalculatedPriceCollection;

    public function getCalculatedPrice(): CalculatedPrice;

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void;

    public function getListingPriceDefinition(\Shopware\Core\Framework\Context $context): PriceDefinition;

    public function getPriceRuleDefinitions(\Shopware\Core\Framework\Context $context): PriceDefinitionCollection;

    public function getPriceDefinition(Context $context): PriceDefinition;
}
