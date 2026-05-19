<?php declare(strict_types=1);

namespace Shopware\Core\Content\MeasurementSystem\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class MeasurementUnitsField extends ObjectField
{
    protected function getSerializerClass(): string
    {
        return MeasurementUnitsFieldSerializer::class;
    }
}
