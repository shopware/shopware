<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Collection;

use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Struct\CategoryTranslationDetailStruct;
use Shopware\Core\Content\Category\Collection\CategoryBasicCollection;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
