<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element;

use Shopware\Core\Framework\Log\Package;

/**
 * Transparent wrapper preventing mixed Struct/non-Struct arrays from crashing StructEncoder.
 * Removed during JSON serialization.
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
