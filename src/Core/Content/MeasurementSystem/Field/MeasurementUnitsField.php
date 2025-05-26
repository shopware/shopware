<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class MeasurementUnitsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return MeasurementUnitsFieldSerializer::class;
    }
} 