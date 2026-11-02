<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Strict primitive value matching shared by property validation and mapping compatibility.
 * Null policy, unions, and object/class checks belong to the callers.
 *
 * @internal
 */
#[Package('framework')]
final class PrimitiveTypeMatcher
{
    public static function matches(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            // JSON has one numeric type, so `number` admits integers and floats.
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            default => false,
        };
    }
}
