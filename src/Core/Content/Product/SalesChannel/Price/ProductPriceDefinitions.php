<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Framework\Struct\Struct;

class ProductPriceDefinitions extends Struct
{
    /**
     * @var QuantityPriceDefinition
     */
    protected $price;

    /**
     * @var PriceDefinitionCollection
     */
    protected $prices;

    /**
     * @var QuantityPriceDefinition
     */
    protected $listingPrice;

    /**
     * @var QuantityPriceDefinition
     */
    protected $quantityPrice;

    public function __construct(
        QuantityPriceDefinition $price,
        PriceDefinitionCollection $prices,
        QuantityPriceDefinition $listingPrice,
        QuantityPriceDefinition $quantityPrice
    ) {
        $this->price = $price;
        $this->prices = $prices;
        $this->listingPrice = $listingPrice;
        $this->quantityPrice = $quantityPrice;
    }

    public function getPrice(): QuantityPriceDefinition
    {
        return $this->price;
    }

    public function setPrice(QuantityPriceDefinition $price): void
    {
        $this->price = $price;
    }

    public function getPrices(): PriceDefinitionCollection
    {
        return $this->prices;
    }

    public function setPrices(PriceDefinitionCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getListingPrice(): QuantityPriceDefinition
    {
        return $this->listingPrice;
    }

    public function setListingPrice(QuantityPriceDefinition $listingPrice): void
    {
        $this->listingPrice = $listingPrice;
    }

    public function getQuantityPrice(): QuantityPriceDefinition
    {
        return $this->quantityPrice;
    }

    public function setQuantityPrice(QuantityPriceDefinition $quantityPrice): void
    {
        $this->quantityPrice = $quantityPrice;
    }
}
