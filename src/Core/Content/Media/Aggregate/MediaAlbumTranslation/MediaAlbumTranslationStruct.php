<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation;

use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumStruct;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class MediaAlbumTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $mediaAlbumId;

    /**
     * @var int
     */
    protected $catalogId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var MediaAlbumStruct|null
     */
    protected $mediaAlbum;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

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

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getMediaAlbumId(): string
    {
        return $this->mediaAlbumId;
    }

    public function setMediaAlbumId(string $mediaAlbumId): void
    {
        $this->mediaAlbumId = $mediaAlbumId;
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

    public function getMediaAlbum(): ?MediaAlbumStruct
    {
        return $this->mediaAlbum;
    }

    public function setMediaAlbum(MediaAlbumStruct $mediaAlbum): void
    {
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }

    public function getCatalogId(): int
    {
        return $this->catalogId;
    }

    public function setCatalogId(int $catalogId): void
    {
        $this->catalogId = $catalogId;
    }

    public function getCatalog(): ?CatalogStruct
    {
        return $this->catalog;
    }

    public function setCatalog(CatalogStruct $catalog): void
    {
        $this->catalog = $catalog;
    }
}
