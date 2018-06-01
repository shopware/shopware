<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Collection;

use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection;
use Shopware\Core\System\Unit\Struct\UnitDetailStruct;

class UnitDetailCollection extends UnitBasicCollection
{
    /**
     * @var UnitDetailStruct[]
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

    public function getTranslations(): UnitTranslationBasicCollection
    {
        $collection = new UnitTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return UnitDetailStruct::class;
    }
}
