<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element;

use Shopware\Core\Framework\Log\Package;

/**
 * Transparent wrapper for element property values.
 *
 * Prevents mixed arrays (non-Struct + Struct) from crashing StructEncoder's isStructArray() check.
 * The wrapper is removed during JSON serialization via jsonSerialize(), making it transparent
 * in API responses.
 *
 * @internal
 */
#[Package('discovery')]
final class PropertyValue implements \JsonSerializable
{
    public function __construct(
        private readonly mixed $value
    ) {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
