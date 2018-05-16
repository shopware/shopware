<?php declare(strict_types=1);

namespace Shopware\System\Listing\Struct;

use Shopware\System\Listing\Aggregate\ListingFacetTranslation\Collection\ListingFacetTranslationBasicCollection;

class ListingFacetDetailStruct extends ListingFacetBasicStruct
{
    /**
     * @var ListingFacetTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new ListingFacetTranslationBasicCollection();
    }

    public function getTranslations(): ListingFacetTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(ListingFacetTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
