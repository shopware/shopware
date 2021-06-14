<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Exception\UnexpectedFieldConfigValueType;
use Shopware\Core\Framework\Struct\Struct;

class FieldConfig extends Struct
{
    public const SOURCE_STATIC = 'static';
    public const SOURCE_MAPPED = 'mapped';
    public const SOURCE_PRODUCT_STREAM = 'product_stream';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $source;

    /**
     * @var array|bool|float|int|string|null
     */
    protected $value;

    /**
     * @param array|bool|float|int|string|null $value
     */
    public function __construct(string $name, string $source, $value)
    {
        $this->name = $name;
        $this->source = $source;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @return array|bool|float|int|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    public function getArrayValue(): array
    {
        if (\is_array($this->value)) {
            return $this->value;
        }

        throw new UnexpectedFieldConfigValueType($this->name, 'array', \gettype($this->value));
    }

    public function getStringValue(): string
    {
        if (!\is_array($this->value)) {
            return (string) $this->value;
        }

        throw new UnexpectedFieldConfigValueType($this->name, 'string', \gettype($this->value));
    }

    public function getIntValue(): int
    {
        if (!\is_array($this->value)) {
            return (int) $this->value;
        }

        throw new UnexpectedFieldConfigValueType($this->name, 'int', \gettype($this->value));
    }

    public function getFloatValue(): float
    {
        if (!\is_array($this->value)) {
            return (float) $this->value;
        }

        throw new UnexpectedFieldConfigValueType($this->name, 'float', \gettype($this->value));
    }

    public function getBoolValue(): bool
    {
        return (bool) $this->value;
    }

    public function isStatic(): bool
    {
        return $this->source === self::SOURCE_STATIC;
    }

    public function isMapped(): bool
    {
        return $this->source === self::SOURCE_MAPPED;
    }

    public function isProductStream(): bool
    {
        return $this->source === self::SOURCE_PRODUCT_STREAM;
    }

    public function getApiAlias(): string
    {
        return 'cms_data_resolver_field_config';
    }
}
