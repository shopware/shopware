<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation;

use Shopware\Core\Content\Catalog\CatalogStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class CatalogTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $catalogId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var CatalogStruct|null
     */
    protected $catalog;

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

    public function getCatalogId(): string
    {
        return $this->catalogId;
    }

    public function setCatalogId(string $catalogId): void
    {
        $this->catalogId = $catalogId;
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

    public function getCatalog(): ?CatalogStruct
    {
        return $this->catalog;
    }

    public function setCatalog(CatalogStruct $catalog): void
    {
        $this->catalog = $catalog;
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
