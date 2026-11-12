<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Codec;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * A value constraint over ONE element's raw array: every `properties` key the element's own `component`
 * declares as a primitive-typed property must carry a value of that type.
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
}
