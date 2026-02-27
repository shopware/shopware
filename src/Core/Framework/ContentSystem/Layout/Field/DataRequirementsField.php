<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * Field for storing data requirements map (JSON to array<string, DataRequirement>).
 *
 * @internal
 */
#[Package('discovery')]
class DataRequirementsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return DataRequirementsFieldSerializer::class;
    }
}
