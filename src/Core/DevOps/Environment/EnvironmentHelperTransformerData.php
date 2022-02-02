<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

class EnvironmentHelperTransformerData
{
    private string $key;

    /**
     * @var bool|float|int|string|null
     */
    private $value;

    /**
     * @var bool|float|int|string|null
     */
    private $default;

    public function __construct(string $key, $value, $default)
    {
        $this->key = $key;
        $this->value = $value;
        $this->default = $default;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($default): void
    {
        $this->default = $default;
    }
}
