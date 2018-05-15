<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryArea;

use Shopware\System\Country\Definition\CountryAreaDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
