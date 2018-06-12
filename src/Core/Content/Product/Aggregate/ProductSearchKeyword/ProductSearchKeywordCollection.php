<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword;

use Shopware\Core\Framework\ORM\EntityCollection;

class ProductSearchKeywordCollection extends EntityCollection
{
    /**
     * @var ProductSearchKeywordStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductSearchKeywordStruct
    {
        return parent::get($id);
    }

    public function current(): ProductSearchKeywordStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductSearchKeywordStruct $productSearchKeyword) {
            return $productSearchKeyword->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductSearchKeywordStruct $productSearchKeyword) use ($id) {
            return $productSearchKeyword->getLanguageId() === $id;
        });
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductSearchKeywordStruct $productSearchKeyword) {
            return $productSearchKeyword->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductSearchKeywordStruct $productSearchKeyword) use ($id) {
            return $productSearchKeyword->getProductId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordStruct::class;
    }
}
