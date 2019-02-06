<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Listing\ListingFacetEntity;

class ListingFacetTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $listingFacetId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var ListingFacetEntity|null
     */
    protected $listingFacet;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getListingFacetId(): string
    {
        return $this->listingFacetId;
    }

    public function setListingFacetId(string $listingFacetId): void
    {
        $this->listingFacetId = $listingFacetId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getListingFacet(): ?ListingFacetEntity
    {
        return $this->listingFacet;
    }

    public function setListingFacet(ListingFacetEntity $listingFacet): void
    {
        $this->listingFacet = $listingFacet;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}
