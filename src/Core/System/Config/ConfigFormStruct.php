<?php declare(strict_types=1);

namespace Shopware\Core\System\Config;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginStruct;

class ConfigFormStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $pluginId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var string|null
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var ConfigFormStruct|null
     */
    protected $parent;

    /**
     * @var PluginStruct|null
     */
    protected $plugin;

    /**
     * @var EntitySearchResult|null
     */
    protected $children;

    /**
     * @var EntitySearchResult|null
     */
    protected $fields;

    /**
     * @var EntitySearchResult|null
     */
    protected $translations;

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(?string $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getParent(): ?ConfigFormStruct
    {
        return $this->parent;
    }

    public function setParent(?ConfigFormStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getPlugin(): ?PluginStruct
    {
        return $this->plugin;
    }

    public function setPlugin(PluginStruct $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getChildren(): ?EntitySearchResult
    {
        return $this->children;
    }

    public function setChildren(EntitySearchResult $children): void
    {
        $this->children = $children;
    }

    public function getFields(): ?EntitySearchResult
    {
        return $this->fields;
    }

    public function setFields(EntitySearchResult $fields): void
    {
        $this->fields = $fields;
    }

    public function getTranslations(): ?EntitySearchResult
    {
        return $this->translations;
    }

    public function setTranslations(EntitySearchResult $translations): void
    {
        $this->translations = $translations;
    }
}
