<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Entity\Entity;

class ShopTemplateBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $pluginId;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $emotion;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $author;

    /**
     * @var string|null
     */
    protected $license;

    /**
     * @var bool
     */
    protected $esi;

    /**
     * @var bool
     */
    protected $styleSupport;

    /**
     * @var int
     */
    protected $version;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(?string $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmotion(): bool
    {
        return $this->emotion;
    }

    public function setEmotion(bool $emotion): void
    {
        $this->emotion = $emotion;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(?string $license): void
    {
        $this->license = $license;
    }

    public function getEsi(): bool
    {
        return $this->esi;
    }

    public function setEsi(bool $esi): void
    {
        $this->esi = $esi;
    }

    public function getStyleSupport(): bool
    {
        return $this->styleSupport;
    }

    public function setStyleSupport(bool $styleSupport): void
    {
        $this->styleSupport = $styleSupport;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
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
}
