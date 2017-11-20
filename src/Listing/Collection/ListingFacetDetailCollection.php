<?php declare(strict_types=1);

namespace Shopware\Listing\Collection;

use Shopware\Listing\Struct\ListingFacetDetailStruct;

class ListingFacetDetailCollection extends ListingFacetBasicCollection
{
    /**
     * @var ListingFacetDetailStruct[]
     */
    protected $elements = [];

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
