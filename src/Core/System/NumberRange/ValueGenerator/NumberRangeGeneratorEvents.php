<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

class NumberRangeGeneratorEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NUMBER_RANGE_GENERATOR_START = 'number_range_generator.start';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    public const NUMBER_RANGE_GENERATOR_END = 'number_range_generator.end';
}
