<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Processing\Mapping;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @phpstan-type MappingArray array{key: string, mappedKey?: string, position?: int, default?: mixed, mappedDefault?: mixed, requiredByUser?: bool, useDefaultValue?: bool, defaultValue?: mixed}
 */
#[Package('system-settings')]
class Mapping extends Struct
{
    protected string $mappedKey;

    public function __construct(
        protected string $key,
        ?string $mappedKey = null,
        protected int $position = 0,
        protected mixed $default = null,
        protected mixed $mappedDefault = null,
        protected bool $requiredByUser = false,
        protected bool $useDefaultValue = false,
        protected mixed $defaultValue = null
    ) {
        $this->mappedKey = $mappedKey ?? $key;
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
     * @param MappingArray $data
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
