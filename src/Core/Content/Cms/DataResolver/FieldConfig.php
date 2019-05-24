<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

class FieldConfig implements \JsonSerializable
{
    public const SOURCE_STATIC = 'static';
    public const SOURCE_MAPPED = 'mapped';

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $source;

    /**
     * @var mixed
     */
    private $value;

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

    public function getValue()
    {
        return $this->value;
    }

    public function isStatic(): bool
    {
        return $this->source === self::SOURCE_STATIC;
    }

    public function isMapped(): bool
    {
        return $this->source === self::SOURCE_MAPPED;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
