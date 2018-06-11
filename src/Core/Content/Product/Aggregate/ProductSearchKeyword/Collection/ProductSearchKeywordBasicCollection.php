<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Collection;

use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Struct\ProductSearchKeywordBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class ProductSearchKeywordBasicCollection extends EntityCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\Struct\ProductSearchKeywordBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductSearchKeywordBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductSearchKeywordBasicStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductSearchKeywordBasicStruct $productSearchKeyword) {
            return $productSearchKeyword->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductSearchKeywordBasicStruct $productSearchKeyword) use ($id) {
            return $productSearchKeyword->getLanguageId() === $id;
        });
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductSearchKeywordBasicStruct $productSearchKeyword) {
            return $productSearchKeyword->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductSearchKeywordBasicStruct $productSearchKeyword) use ($id) {
            return $productSearchKeyword->getProductId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordBasicStruct::class;
    }
}
