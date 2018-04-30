<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Collection;

use Shopware\Api\Listing\Struct\ListingFacetDetailStruct;

class ListingFacetDetailCollection extends ListingFacetBasicCollection
{
    /**
     * @var ListingFacetDetailStruct[]
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

    public function getTranslations(): ListingFacetTranslationBasicCollection
    {
        $collection = new ListingFacetTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ListingFacetDetailStruct::class;
    }
}
