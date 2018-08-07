<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;
use Shopware\Core\System\Listing\ListingFacetStruct;

class ListingFacetTranslationStruct extends Entity
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

    /**
     * @var ListingFacetStruct|null
     */
    protected $listingFacet;

    /**
     * @var LanguageStruct|null
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getListingFacet(): ?ListingFacetStruct
    {
        return $this->listingFacet;
    }

    public function setListingFacet(ListingFacetStruct $listingFacet): void
    {
        $this->listingFacet = $listingFacet;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
