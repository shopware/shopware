<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductPriceBasicStruct;

class ProductPriceBasicCollection extends EntityCollection
{
    /**
     * @var ProductPriceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductPriceBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductPriceBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupIds(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getCustomerGroupId();
        });
    }

    public function filterByCustomerGroupId(string $id): self
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($id) {
            return $productPrice->getCustomerGroupId() === $id;
        });
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($id) {
            return $productPrice->getProductId() === $id;
        });
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ProductPriceBasicStruct $productPrice) {
                return $productPrice->getCustomerGroup();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceBasicStruct::class;
    }
}
