<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Struct\Struct;

/**
 * @package system-settings
 */
class Mapping extends Struct
{
    protected string $key;

    protected string $mappedKey;

    protected mixed $default;

    protected mixed $mappedDefault;

    protected bool $requiredByUser;

    protected bool $useDefaultValue;

    protected mixed $defaultValue;

    protected int $position;

    public function __construct(
        string $key,
        ?string $mappedKey = null,
        int $position = 0,
        mixed $default = null,
        mixed $mappedDefault = null,
        bool $requiredByUser = false,
        bool $useDefaultValue = false,
        mixed $defaultValue = null
    ) {
        $this->key = $key;
        $this->mappedKey = $mappedKey ?? $key;
        $this->default = $default;
        $this->mappedDefault = $mappedDefault;
        $this->requiredByUser = $requiredByUser;
        $this->useDefaultValue = $useDefaultValue;
        $this->defaultValue = $defaultValue;
        $this->position = $position;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getMappedKey(): string
    {
        return $this->mappedKey;
    }

    public function isRequiredByUser(): bool
    {
        return $this->requiredByUser;
    }

    public function isUseDefaultValue(): bool
    {
        return $this->useDefaultValue;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['key'])) {
            throw new \InvalidArgumentException('key is required in mapping');
        }

        $mapping = new self($data['key']);
        $mapping->assign($data);

        return $mapping;
    }
}
