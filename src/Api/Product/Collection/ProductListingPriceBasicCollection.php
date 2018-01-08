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

    public function get(string $id): ? ProductListingPriceBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductListingPriceBasicStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($id) {
            return $productListingPrice->getProductId() === $id;
        });
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (ProductListingPriceBasicStruct $productListingPrice) {
            return $productListingPrice->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (ProductListingPriceBasicStruct $productListingPrice) use ($id) {
            return $productListingPrice->getCustomerGroupId() === $id;
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
