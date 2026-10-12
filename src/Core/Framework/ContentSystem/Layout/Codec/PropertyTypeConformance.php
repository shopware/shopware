<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * A value constraint over ONE element's raw array: every `properties` key the element's own `component`
 * declares must carry a value {@see PropertyType::admits()} accepts, and a translatable property's language
 * map must additionally be keyed by language ids.
 *
 * It takes the whole element rather than the `properties` map alone because the declaration it judges against
 * is reached through `component`, which only the element level carries. {@see PropertyTypeConformanceValidator}
 * holds the element-type registry; {@see StoredTreeConstraints} only attaches this and stays blind to types.
 *
 * @internal
 */
#[Package('framework')]
#[\Attribute]
final class PropertyTypeConformance extends Constraint
{
    public string $message = 'Property "{{ key }}" is declared as "{{ declaredType }}" but carries a value of type "{{ actualType }}".';

    public string $languageKeyMessage = 'Property "{{ key }}" is translatable, so every key of its value must be a language id in lowercase UUID hex; "{{ languageKey }}" is not.';
}
