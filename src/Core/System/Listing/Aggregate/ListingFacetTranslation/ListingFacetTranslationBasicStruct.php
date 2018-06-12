<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\ORM\Entity;

class ListingFacetTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $listingFacetId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    public function getListingFacetId(): string
    {
        return $this->listingFacetId;
    }

    public function setListingFacetId(string $listingFacetId): void
    {
        $this->listingFacetId = $listingFacetId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
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
