<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Product\Struct\ProductPriceBasicStruct;

class ProductPriceBasicCollection extends EntityCollection
{
    /**
     * @var ProductPriceBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductPriceBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductPriceBasicStruct
    {
        return parent::current();
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getCustomerGroupUuid() === $uuid;
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductPriceBasicStruct $productPrice) {
            return $productPrice->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductPriceBasicCollection
    {
        return $this->filter(function (ProductPriceBasicStruct $productPrice) use ($uuid) {
            return $productPrice->getProductUuid() === $uuid;
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
