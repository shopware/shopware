<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class EnvironmentHelperTransformerData
{
    public function __construct(
        private readonly string $key,
        private bool|float|int|string|null $value,
        private bool|float|int|string|null $default
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): bool|float|int|string|null
    {
        return $this->value;
    }

    public function setValue(bool|float|int|string|null $value): void
    {
        $this->value = $value;
    }

    public function getDefault(): bool|float|int|string|null
    {
        return $this->default;
    }

    public function setDefault(bool|float|int|string|null $default): void
    {
        $this->default = $default;
    }
}
