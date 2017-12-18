<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryArea;

use Shopware\Api\Country\Definition\CountryAreaDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
