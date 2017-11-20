<?php declare(strict_types=1);

namespace Shopware\Snippet\Struct;

use Shopware\Api\Entity\Entity;

class SnippetBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $shopUuid;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var int
     */
    protected $shopId;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var bool|null
     */
    protected $dirty;

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function setShopId(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
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

    public function getDirty(): ?bool
    {
        return $this->dirty;
    }

    public function setDirty(?bool $dirty): void
    {
        $this->dirty = $dirty;
    }
}
