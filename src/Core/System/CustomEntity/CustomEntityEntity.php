<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CustomEntityEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected bool $cmsAware;

    protected bool $storeApiAware;

    protected ?string $appId = null;

    protected ?string $pluginId = null;

    protected bool $customFieldsAware;

    protected ?string $labelProperty = null;

    /**
     * @var array<mixed>
     */
    protected array $fields;

    /**
     * @var array<string, array<mixed>>|null
     */
    protected ?array $flags;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStoreApiAware(): bool
    {
        return $this->storeApiAware;
    }

    public function setStoreApiAware(bool $storeApiAware): void
    {
        $this->storeApiAware = $storeApiAware;
    }

    public function getCmsAware(): bool
    {
        return $this->cmsAware;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
    {
        $this->appId = $appId;
    }

    public function getPluginId(): ?string
    {
        return $this->pluginId;
    }

    public function setPluginId(?string $pluginId): void
    {
        $this->pluginId = $pluginId;
    }

    /**
     * @return array<mixed>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @param array<mixed> $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * @return array<string, array<mixed>>|null
     */
    public function getFlags(): ?array
    {
        return $this->flags;
    }

    /**
     * @param array<string, array<mixed>>|null $flags
     */
    public function setFlags(?array $flags): void
    {
        $this->flags = $flags;
    }

    public function getCustomFieldsAware(): bool
    {
        return $this->customFieldsAware;
    }

    public function setCustomFieldsAware(bool $customFieldsAware): void
    {
        $this->customFieldsAware = $customFieldsAware;
    }

    public function getLabelProperty(): ?string
    {
        return $this->labelProperty;
    }

    public function setLabelProperty(?string $labelProperty): void
    {
        $this->labelProperty = $labelProperty;
    }
}
