<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Listing\ListingFacetEntity;

class ListingFacetTranslationEntity extends Entity
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
     * @var string|null
     */
    protected $name;

    /**
     * @var ListingFacetEntity|null
     */
    protected $listingFacet;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

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

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
