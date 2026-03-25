<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing context consumers map (JSON to array<string, ContextConsumer>).
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
