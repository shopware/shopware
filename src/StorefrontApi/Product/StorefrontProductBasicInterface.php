<?php

namespace Shopware\StorefrontApi\Product;

use Shopware\Api\Product\Collection\ProductMediaBasicCollection;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Price\Struct\CalculatedPriceCollection;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Context\Struct\ShopContext;

interface StorefrontProductBasicInterface
{

    public function getCover(): ?ProductMediaBasicStruct;

    public function getMedia(): ProductMediaBasicCollection;

    public function setMedia(ProductMediaBasicCollection $media): void;

    public function isAvailable(): bool;

    public function getCalculatedListingPrice(): CalculatedPrice;

    public function setCalculatedListingPrice(CalculatedPrice $calculatedListingPrice): void;

    public function setCalculatedContextPrices(CalculatedPriceCollection $prices): void;

    public function getCalculatedContextPrices(): CalculatedPriceCollection;

    public function getCalculatedPrice(): CalculatedPrice;

    public function setCalculatedPrice(CalculatedPrice $calculatedPrice): void;

    public function getListingPriceDefinition(ShopContext $context): PriceDefinition;

    public function getContextPriceDefinitions(ShopContext $context): PriceDefinitionCollection;

    public function getPriceDefinition(ShopContext $context): PriceDefinition;
}