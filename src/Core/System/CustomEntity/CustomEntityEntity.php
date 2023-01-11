<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @package core
 */
class CustomEntityEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected bool $cmsAware;

    protected bool $storeApiAware;

    protected ?string $appId;

    /**
     * @var array<mixed>
     */
    protected array $fields;

    /**
     * @var array<int, string>
     */
    protected array $flags;

    /**
     * @var array<string, array<mixed>>
     */
    protected array $flagsConfig;

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
     * @return array<int, string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * @param array<int, string> $flags
     */
    public function setFlags(array $flags): void
    {
        $this->flags = $flags;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function getFlagsConfig(): array
    {
        return $this->flagsConfig;
    }

    /**
     * @param array<string, array<mixed>> $flagsConfig
     */
    public function setFlagsConfig(array $flagsConfig): void
    {
        $this->flagsConfig = $flagsConfig;
    }
}
