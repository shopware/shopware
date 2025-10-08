<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing context consumers map.
 *
 * Handles conversion between JSON storage and array<string, ContextConsumer>
 * during entity hydration/persistence.
 *
 * @internal
 */
#[Package('discovery')]
class ContextConsumersField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ContextConsumersFieldSerializer::class;
    }
}
