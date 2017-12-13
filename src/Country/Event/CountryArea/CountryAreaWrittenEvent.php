<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryArea;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Country\Definition\CountryAreaDefinition;

class CountryAreaWrittenEvent extends WrittenEvent
{
    const NAME = 'country_area.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryAreaDefinition::class;
    }
}
