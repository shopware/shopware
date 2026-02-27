<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Immutable value object for placeholder values used in content rendering.
 *
 * Replaces the combined EntityIdMap + ParameterMap concept with a simpler flat map.
 * Placeholder replacement doesn't care about the source of values (entity IDs vs URL parameters).
 */
#[Package('discovery')]
final readonly class PlaceholderValues
{
    /**
     * @param array<string, string|int|bool|float> $values
     */
    private function __construct(private array $values)
    {
    }

    /**
     * @param array<string, string|int|bool|float> $values
     *
     * @throws ContentSystemException If keys are not strings or values are not scalar
     */
    public static function from(array $values): self
    {
        foreach ($values as $key => $value) {
            if (!\is_string($key)) {
                throw ContentSystemException::invalidMapKey(
                    'PlaceholderValues',
                    get_debug_type($key)
                );
            }

            if (!\is_scalar($value)) {
                throw ContentSystemException::invalidMapValue(
                    'PlaceholderValues',
                    $key,
                    'scalar',
                    get_debug_type($value)
                );
            }
        }

        return new self($values);
    }

    /**
     * @return array<string, string|int|bool|float>
     */
    public function all(): array
    {
        return $this->values;
    }
}
