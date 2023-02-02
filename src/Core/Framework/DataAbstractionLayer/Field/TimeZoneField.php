<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;

class TimeZoneField extends StringField
{
    protected function getSerializerClass(): string
    {
        return TimeZoneFieldSerializer::class;
    }
}
