<?php declare(strict_types=1);

namespace Shopware\Category\Collection;

use Shopware\Category\Struct\CategoryTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class CategoryTranslationDetailCollection extends CategoryTranslationBasicCollection
{
    /**
     * @var CategoryTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCategories(): CategoryBasicCollection
    {
        return new CategoryBasicCollection(
            $this->fmap(function (CategoryTranslationDetailStruct $categoryTranslation) {
                return $categoryTranslation->getCategory();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (CategoryTranslationDetailStruct $categoryTranslation) {
                return $categoryTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationDetailStruct::class;
    }
}
