<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing element slots map with recursive ContentElement tree.
 *
 * Handles conversion between JSON storage and ElementSlots object during
 * entity hydration/persistence. Supports recursive deserialization of nested
 * ContentElement structures.
 *
 * @internal
 */
#[Package('discovery')]
class ElementSlotsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementSlotsFieldSerializer::class;
    }
}
