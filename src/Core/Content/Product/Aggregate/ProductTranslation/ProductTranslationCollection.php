<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation;


use Shopware\Core\Framework\ORM\EntityCollection;

class ProductTranslationCollection extends EntityCollection
{
    /**
     * @var ProductTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ProductTranslationStruct
    {
        return parent::current();
    }

    public function getProductIds(): array
    {
        return $this->fmap(function (ProductTranslationStruct $productTranslation) {
            return $productTranslation->getProductId();
        });
    }

    public function filterByProductId(string $id): self
    {
        return $this->filter(function (ProductTranslationStruct $productTranslation) use ($id) {
            return $productTranslation->getProductId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductTranslationStruct $productTranslation) {
            return $productTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductTranslationStruct $productTranslation) use ($id) {
            return $productTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationStruct::class;
    }
}
