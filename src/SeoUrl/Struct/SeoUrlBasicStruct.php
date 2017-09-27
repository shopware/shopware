<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Struct;

use Shopware\Framework\Struct\Struct;

class SeoUrlBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $seoHash;

    /**
     * @var string
     */
    protected $shopUuid;

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
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string
     */
    protected $url;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getSeoHash(): string
    {
        return sha1($this->seoPathInfo);
    }

    public function setSeoHash(string $seoHash): void
    {
        $this->seoHash = $seoHash;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
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
}
