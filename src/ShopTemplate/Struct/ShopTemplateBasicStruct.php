<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Struct;

use Shopware\Framework\Struct\Struct;

class ShopTemplateBasicStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $name;

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
     * @var bool
     */
    protected $emotion;

    /**
     * @var int|null
     */
    protected $pluginId;

    /**
     * @var string|null
     */
    protected $pluginUuid;

    /**
     * @var int|null
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $parentUuid;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
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

    public function getEmotion(): bool
    {
        return $this->emotion;
    }

    public function setEmotion(bool $emotion): void
    {
        $this->emotion = $emotion;
    }

    public function getPluginId(): ?int
    {
        return $this->pluginId;
    }

    public function setPluginId(?int $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getPluginUuid(): ?string
    {
        return $this->pluginUuid;
    }

    public function setPluginUuid(?string $pluginUuid): void
    {
        $this->pluginUuid = $pluginUuid;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParentUuid(): ?string
    {
        return $this->parentUuid;
    }

    public function setParentUuid(?string $parentUuid): void
    {
        $this->parentUuid = $parentUuid;
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
