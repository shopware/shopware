<?php declare(strict_types=1);

namespace Shopware\Listing\Struct;

use Shopware\Api\Entity\Entity;

class ListingFacetTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $listingFacetUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    public function getListingFacetUuid(): string
    {
        return $this->listingFacetUuid;
    }

    public function setListingFacetUuid(string $listingFacetUuid): void
    {
        $this->listingFacetUuid = $listingFacetUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
