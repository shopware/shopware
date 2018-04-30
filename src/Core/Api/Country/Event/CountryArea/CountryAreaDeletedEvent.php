<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryArea;

use Shopware\Api\Country\Definition\CountryAreaDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class CountryAreaDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country_area.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryAreaDefinition::class;
    }
}
