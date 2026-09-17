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

    private function isPrimitive(string $type): bool
    {
        return \in_array($type, PropertyType::PRIMITIVE_TYPES, true);
    }
}
