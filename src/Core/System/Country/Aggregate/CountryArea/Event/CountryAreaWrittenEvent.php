<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaDefinition;

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
