<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Environment;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class EnvironmentHelperTransformerData
{
    /**
     * @param bool|float|int|string|null $value
     * @param bool|float|int|string|null $default
     */
    public function __construct(
        private readonly string $key,
        /** @deprecated tag:v6.7.0 - Will be natively typed */
        private $value,
        /** @deprecated tag:v6.7.0 - Will be natively typed */
        private $default
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will return native type
     *
     * @return bool|float|int|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:parameter-name-change - Parameter will be natively typed
     *
     * @param bool|float|int|string|null $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will return native type
     *
     * @return bool|float|int|string|null
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @deprecated tag:v6.7.0 - reason:parameter-name-change - Parameter will be natively typed
     *
     * @param bool|float|int|string|null $default
     */
    public function setDefault($default): void
    {
        $this->default = $default;
    }
}
