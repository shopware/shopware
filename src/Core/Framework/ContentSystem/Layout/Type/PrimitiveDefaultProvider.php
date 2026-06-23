<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type;

use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * The single definition of a content element type's primitive property defaults: the non-null default of every
 * primitive property of a registered type, keyed by property key. Both the layout mutations (seeding a scaffolded
 * or replaced element) and the write-boundary {@see LayoutDefaultSeeder} read the rule here, so "a type's
 * primitive defaults" is defined once.
 *
 * The caller guarantees the type is registered; reference (FQCN) properties and primitives without a default are
 * skipped.
 *
 * @internal
 */
#[Package('framework')]
final class PrimitiveDefaultProvider
{
    /**
     * @return array<string, string|int|float|bool>
     */
    public function forType(AbstractContentSystemElementTypeRegistry $registry, string $type): array
    {
        $defaults = [];

        foreach ($registry->get($type)->properties() as $key => $property) {
            $propertyType = $property->type();

            if (!$propertyType->isPrimitive()) {
                continue;
            }

            $default = $propertyType->default();

            if ($default === null) {
                continue;
            }

            $defaults[$key] = $default;
        }

        return $defaults;
    }
}
