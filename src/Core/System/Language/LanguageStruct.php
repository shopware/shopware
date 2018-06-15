<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\System\Locale\LocaleStruct;

class LanguageStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $localeId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $localeVersionId;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var LocaleStruct
     */
    protected $locale;

    /**
     * @var LanguageStruct|null
     */
    protected $parent;

    /**
     * @var EntitySearchResult|null
     */
    protected $children;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getLocaleId(): string
    {
        return $this->localeId;
    }

    public function setLocaleId(string $localeId): void
    {
        $this->localeId = $localeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLocaleVersionId(): string
    {
        return $this->localeVersionId;
    }

    public function setLocaleVersionId(string $localeVersionId): void
    {
        $this->localeVersionId = $localeVersionId;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
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

    public function getLocale(): LocaleStruct
    {
        return $this->locale;
    }

    public function setLocale(LocaleStruct $locale): void
    {
        $this->locale = $locale;
    }

    public function getParent(): ?LanguageStruct
    {
        return $this->parent;
    }

    public function setParent(LanguageStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?EntitySearchResult
    {
        return $this->children;
    }

    public function setChildren(EntitySearchResult $children): void
    {
        $this->children = $children;
    }
}
