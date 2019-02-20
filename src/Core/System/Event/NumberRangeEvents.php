<?php declare(strict_types=1);

namespace Shopware\Core\System\Event;

class NumberRangeEvents
{
    /**
     * @Event("Shopware\Core\System\NumberRange\NumberRangeGeneratedEvent")
     */
    public const NUMBER_RANGE_GENERATED = 'number_range.generated';
}
