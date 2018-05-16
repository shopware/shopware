<?php declare(strict_types=1);

namespace Shopware\System\Currency\Collection;

use Shopware\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;
use Shopware\System\Currency\Struct\CurrencyDetailStruct;

class CurrencyDetailCollection extends CurrencyBasicCollection
{
    /**
     * @var CurrencyDetailStruct[]
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

    public function getTranslations(): CurrencyTranslationBasicCollection
    {
        $collection = new CurrencyTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CurrencyDetailStruct::class;
    }
}
