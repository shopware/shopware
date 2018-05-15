<?php declare(strict_types=1);

namespace Shopware\System\Listing\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Listing\Struct\ListingSortingTranslationDetailStruct;

class ListingSortingTranslationDetailCollection extends ListingSortingTranslationBasicCollection
{
    /**
     * @var ListingSortingTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return new ListingSortingBasicCollection(
            $this->fmap(function (ListingSortingTranslationDetailStruct $listingSortingTranslation) {
                return $listingSortingTranslation->getListingSorting();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ListingSortingTranslationDetailStruct $listingSortingTranslation) {
                return $listingSortingTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingTranslationDetailStruct::class;
    }
}
