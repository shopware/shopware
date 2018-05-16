<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryArea\Event;

use Shopware\System\Country\Aggregate\CountryArea\CountryAreaDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CountryAreaWrittenEvent extends WrittenEvent
{
    public const NAME = 'country_area.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryAreaDefinition::class;
    }
}
