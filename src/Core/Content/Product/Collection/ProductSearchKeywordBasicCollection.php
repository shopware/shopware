<?php declare(strict_types=1);

namespace Shopware\Content\Product\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Content\Product\Struct\ProductSearchKeywordBasicStruct;

class ProductSearchKeywordBasicCollection extends EntityCollection
{
    /**
     * @var ProductSearchKeywordBasicStruct[]
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
