<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing element slots with recursive ContentElement tree (JSON to ElementSlots).
 *
 * @internal
 */
#[Package('framework')]
class ElementSlotsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementSlotsFieldSerializer::class;
    }
}
