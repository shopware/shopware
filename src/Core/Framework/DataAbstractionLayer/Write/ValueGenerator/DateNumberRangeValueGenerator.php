<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

class DateNumberRangeValueGenerator extends ValueGenerator
{
    protected $generatorId = 'date_number_range_value_generator';

    public function generate($value = null): string
    {
        return  $this->configuration->getPrefix() . ($value + $this->incrementBy($value)) .
            $this->configuration->getSuffix() . '_' . date('DMY');
    }
}
