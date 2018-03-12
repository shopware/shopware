<?php declare(strict_types=1);

namespace Shopware\Api\Language\Collection;

use Shopware\Api\Language\Struct\LanguageDetailStruct;
use Shopware\Api\Locale\Collection\LocaleBasicCollection;


class LanguageDetailCollection extends LanguageBasicCollection
{
    /**
     * @var LanguageDetailStruct[]
     */
    protected $elements = [];

    protected function getExpectedClass(): string
    {
        return LanguageDetailStruct::class;
    }

    public function getParents(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function(LanguageDetailStruct $language) {
                return $language->getParent();
            })
        );
    }

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function(LanguageDetailStruct $language) {
                return $language->getLocale();
            })
        );
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): LanguageBasicCollection
    {
        $collection = new LanguageBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }
        return $collection;
    }
}