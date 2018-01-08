<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductListingPriceBasicStruct;

class ProductListingPriceBasicCollection extends EntityCollection
{
    /**
     * @var ProductListingPriceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductListingPriceBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductListingPriceBasicStruct
    {
        return parent::current();
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): self
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($uuid) {
            return $productListingPrice->getProductUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): self
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($uuid) {
            return $productListingPrice->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
                return $productListingPrice->getCustomerGroup();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductListingPriceBasicStruct::class;
    }
}
