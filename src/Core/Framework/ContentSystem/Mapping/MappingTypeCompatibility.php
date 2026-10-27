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
     * @param string $candidateValueType the candidate's effective provider output type
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

        return \in_array($declaredType, self::compatibleTypes($candidateValueType), true);
    }

    /**
     * Lists every declared property type accepted by the same rule as {@see permits()}.
     * The Administration uses this server-computed set to present compatible candidates without reimplementing
     * primitive, object, or PHP class assignability rules in the browser.
     *
     * @return list<string>
     */
    public static function compatibleTypes(string $candidateValueType): array
    {
        if (self::isPrimitiveType($candidateValueType)) {
            return [$candidateValueType];
        }

        if ($candidateValueType === self::UNCONSTRAINED_OBJECT_TYPE) {
            return [self::UNCONSTRAINED_OBJECT_TYPE];
        }

        $types = [$candidateValueType];

        if (class_exists($candidateValueType)) {
            $types = [
                ...$types,
                ...array_values(class_parents($candidateValueType) ?: []),
                ...array_values(class_implements($candidateValueType) ?: []),
            ];
        } elseif (interface_exists($candidateValueType)) {
            $types = [
                ...$types,
                ...array_values(class_implements($candidateValueType) ?: []),
            ];
        }

        $types[] = self::UNCONSTRAINED_OBJECT_TYPE;

        return array_values(array_unique($types));
    }

    public static function isPrimitiveType(string $type): bool
    {
        return \in_array($type, PropertyType::PRIMITIVE_TYPES, true);
    }

    /**
     * Whether a RUNTIME VALUE is of `$type`, in the same type vocabulary {@see permits()} speaks.
     *
     * The render path's counterpart to permits(): a provider declares the type it returns, and this is what
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
}
