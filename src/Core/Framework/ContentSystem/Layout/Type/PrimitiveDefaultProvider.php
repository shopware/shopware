<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type;

use Shopware\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * The single definition of a content element type's stored property defaults: the non-null default of every
 * primitive property and every nested object member of a registered type, keyed by property key. Both the layout
 * mutations (seeding a scaffolded or replaced element) and the write-boundary {@see LayoutDefaultSeeder} read the
 * rule here, so "a type's defaults" is defined once.
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
     * @return array<string, string|int|float|bool|array<string, mixed>>
     */
    public function forType(AbstractContentSystemElementTypeRegistry $registry, string $type): array
    {
        $defaults = [];

        foreach ($registry->get($type)->properties() as $key => $property) {
            $default = $this->defaultFor($property->type());

            if ($default === null) {
                continue;
            }

            $defaults[$key] = $default;
        }

        return $defaults;
    }

    /**
     * @return string|int|float|bool|array<string, mixed>|null
     */
    private function defaultFor(PropertyType $type): string|int|float|bool|array|null
    {
        if ($type->isPrimitive()) {
            return $type->default();
        }

        $properties = $type->properties();
        if ($properties === null) {
            return null;
        }

        $defaults = [];
        foreach ($properties as $key => $property) {
            $default = $this->defaultFor($property->type());
            if ($default !== null) {
                $defaults[$key] = $default;
            }
        }

        return $defaults === [] ? null : $defaults;
    }
}
