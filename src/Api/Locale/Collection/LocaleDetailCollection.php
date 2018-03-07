<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Locale\Struct\LocaleDetailStruct;

class LocaleDetailCollection extends LocaleBasicCollection
{
    /**
     * @var LocaleDetailStruct[]
     */
    protected $elements = [];

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): LocaleTranslationBasicCollection
    {
        $collection = new LocaleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return LocaleDetailStruct::class;
    }
}
