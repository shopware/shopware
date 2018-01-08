<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductCategoryTreeBasicStruct;

class ProductCategoryTreeBasicCollection extends EntityCollection
{
    /**
     * @var ProductCategoryTreeBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductCategoryTreeBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductCategoryTreeBasicStruct
    {
        return parent::current();
    }

    public function getCategoryIds(): array
    {
        return $this->fmap(function (ProductCategoryTreeBasicStruct $productCategoryTree) {
            return $productCategoryTree->getCategoryId();
        });
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(function (ProductCategoryTreeBasicStruct $productCategoryTree) use ($id) {
            return $productCategoryTree->getCategoryId() === $id;
        });
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductCategoryTreeBasicStruct $productCategoryTree) {
            return $productCategoryTree->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductCategoryTreeBasicStruct $productCategoryTree) use ($id) {
            return $productCategoryTree->getProductId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductCategoryTreeBasicStruct::class;
    }
}
