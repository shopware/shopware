<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;

use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class ProductTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ProductTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductTranslationBasicStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductTranslationBasicStruct $productTranslation) {
            return $productTranslation->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductTranslationBasicStruct $productTranslation) use ($id) {
            return $productTranslation->getProductId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductTranslationBasicStruct $productTranslation) {
            return $productTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductTranslationBasicStruct $productTranslation) use ($id) {
            return $productTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationBasicStruct::class;
    }
}
