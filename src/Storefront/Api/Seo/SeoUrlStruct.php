<?php declare(strict_types=1);

namespace Shopware\Storefront\Api\Seo;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Touchpoint\TouchpointStruct;

class SeoUrlStruct extends Entity
{
    /**
     * @var string
     */
    protected $versionId;

    /**
     * @var string
     */
    protected $touchpointId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $foreignKey;

    /**
     * @var string
     */
    protected $foreignKeyVersionId;

    /**
     * @var string
     */
    protected $pathInfo;

    /**
     * @var string
     */
    protected $seoPathInfo;

    /**
     * @var bool
     */
    protected $isCanonical;

    /**
     * @var bool
     */
    protected $isModified;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var TouchpointStruct|null
     */
    protected $touchpoint;

    public function getVersionId(): string
    {
        return $this->versionId;
    }

    public function setVersionId(string $versionId): void
    {
        $this->versionId = $versionId;
    }

    public function getTouchpointId(): string
    {
        return $this->touchpointId;
    }

    public function setTouchpointId(string $touchpointId): void
    {
        $this->touchpointId = $touchpointId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(string $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
    }

    public function getForeignKeyVersionId(): string
    {
        return $this->foreignKeyVersionId;
    }

    public function setForeignKeyVersionId(string $foreignKeyVersionId): void
    {
        $this->foreignKeyVersionId = $foreignKeyVersionId;
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function setPathInfo(string $pathInfo): void
    {
        $this->pathInfo = $pathInfo;
    }

    public function getSeoPathInfo(): string
    {
        return $this->seoPathInfo;
    }

    public function setSeoPathInfo(string $seoPathInfo): void
    {
        $this->seoPathInfo = $seoPathInfo;
    }

    public function getIsCanonical(): bool
    {
        return $this->isCanonical;
    }

    public function setIsCanonical(bool $isCanonical): void
    {
        $this->isCanonical = $isCanonical;
    }

    public function getIsModified(): bool
    {
        return $this->isModified;
    }

    public function setIsModified(bool $isModified): void
    {
        $this->isModified = $isModified;
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

    public function getTouchpoint(): ?TouchpointStruct
    {
        return $this->touchpoint;
    }

    public function setTouchpoint(TouchpointStruct $touchpoint): void
    {
        $this->touchpoint = $touchpoint;
    }
}
