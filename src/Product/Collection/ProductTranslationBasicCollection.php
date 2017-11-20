<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Product\Struct\ProductTranslationBasicStruct;

class ProductTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ProductTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductTranslationBasicStruct
    {
        return parent::current();
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductTranslationBasicStruct $productTranslation) {
            return $productTranslation->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductTranslationBasicCollection
    {
        return $this->filter(function (ProductTranslationBasicStruct $productTranslation) use ($uuid) {
            return $productTranslation->getProductUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (ProductTranslationBasicStruct $productTranslation) {
            return $productTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): ProductTranslationBasicCollection
    {
        return $this->filter(function (ProductTranslationBasicStruct $productTranslation) use ($uuid) {
            return $productTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationBasicStruct::class;
    }
}
