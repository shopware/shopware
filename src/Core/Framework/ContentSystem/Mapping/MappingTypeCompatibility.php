<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * Decides whether a candidate's effective value type may fill a declared property type.
 *
 * This is the one rule the render path cannot enforce for itself:
 * `Rendering/ContextDeliveryResolver::overlayRootContext()` writes whatever the path resolved to, and the
 * resolved-value index and the encoders carry it onward, so a mapping from `category.name` onto a
 * `MediaCollection` property would serve a string where a collection belongs without anything raising. The
 * write boundary asks this class instead, before the mapping is ever stored.
 *
 * @internal
 */
#[Package('framework')]
final class MappingTypeCompatibility
{
    private const UNCONSTRAINED_OBJECT_TYPE = 'object';

    /**
     * A union is satisfied by any one of its members, matching how the render path treats a union as authored
     * and serves whatever value it holds. Bare `object` names an object without naming which one, so it takes
     * any class-typed candidate and no primitive.
     *
     * @param string|list<string> $declaredType the property's declared `type`, as {@see PropertyType::type()} returns it
     * @param string $candidateValueType the candidate's EFFECTIVE type, after its projection
     */
    public function permits(string|array $declaredType, string $candidateValueType): bool
    {
        if (\is_array($declaredType)) {
            foreach ($declaredType as $member) {
                if ($this->permits($member, $candidateValueType)) {
                    return true;
                }
            }

            return false;
        }

        if ($declaredType === self::UNCONSTRAINED_OBJECT_TYPE) {
            return !$this->isPrimitive($candidateValueType);
        }

        if ($this->isPrimitive($declaredType)) {
            return $declaredType === $candidateValueType;
        }

        return !$this->isPrimitive($candidateValueType)
            && is_a($candidateValueType, $declaredType, true);
    }

    /**
     * Whether a RUNTIME VALUE is of `$type`, in the same type vocabulary {@see permits()} speaks.
     *
     * The render path's counterpart to permits(): a projection declares the type it accepts, and this is what
     * holds a resolved value to that declaration before `project()` is handed it.
     *
     * @param string $type a `PropertyType::PRIMITIVE_TYPES` member, bare `object`, or an FQCN
     */
    public function admits(string $type, mixed $value): bool
    {
        return match ($type) {
            'string' => \is_string($value),
            'integer' => \is_int($value),
            // As in the property specification, `number` covers both, since JSON has one numeric type.
            'number' => \is_int($value) || \is_float($value),
            'boolean' => \is_bool($value),
            self::UNCONSTRAINED_OBJECT_TYPE => \is_object($value),
            default => $value instanceof $type,
        };
    }

    private function isPrimitive(string $type): bool
    {
        return \in_array($type, PropertyType::PRIMITIVE_TYPES, true);
    }
}
