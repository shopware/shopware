<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryArea\CountryAreaDefinition;

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
