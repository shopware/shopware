<?php

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomEntityEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected bool $storeApiAware;

    protected ?string $appId;

    protected array $fields;

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

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(?string $appId): void
    {
        $this->appId = $appId;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }
}
