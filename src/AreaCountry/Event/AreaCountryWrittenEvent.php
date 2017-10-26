<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class AreaCountryWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'area_country.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'area_country';
    }
}
