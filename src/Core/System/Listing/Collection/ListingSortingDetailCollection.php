<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Collection;

use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Core\System\Listing\Struct\ListingSortingDetailStruct;

class ListingSortingDetailCollection extends ListingSortingBasicCollection
{
    /**
     * @var ListingSortingDetailStruct[]
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

    public function getTranslations(): ListingSortingTranslationBasicCollection
    {
        $collection = new ListingSortingTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ListingSortingDetailStruct::class;
    }
}
