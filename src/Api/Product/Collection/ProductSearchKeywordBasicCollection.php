<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductSearchKeywordBasicStruct;

class ProductSearchKeywordBasicCollection extends EntityCollection
{
    /**
     * @var ProductSearchKeywordBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductSearchKeywordBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductSearchKeywordBasicStruct
    {
        return parent::current();
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ProductSearchKeywordBasicStruct $productSearchKeyword) {
            return $productSearchKeyword->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ProductSearchKeywordBasicCollection
    {
        return $this->filter(function (ProductSearchKeywordBasicStruct $productSearchKeyword) use ($uuid) {
            return $productSearchKeyword->getShopUuid() === $uuid;
        });
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductSearchKeywordBasicStruct $productSearchKeyword) {
            return $productSearchKeyword->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductSearchKeywordBasicCollection
    {
        return $this->filter(function (ProductSearchKeywordBasicStruct $productSearchKeyword) use ($uuid) {
            return $productSearchKeyword->getProductUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordBasicStruct::class;
    }
}
