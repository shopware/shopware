<?php declare(strict_types=1);

namespace Shopware\System\Country\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Country\CountryDefinition;

class CountryDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryDefinition::class;
    }
}
