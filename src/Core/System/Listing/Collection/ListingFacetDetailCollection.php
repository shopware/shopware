<?php declare(strict_types=1);

namespace Shopware\System\Listing\Collection;

use Shopware\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationBasicCollection;
use Shopware\System\Listing\Struct\ListingFacetDetailStruct;

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
