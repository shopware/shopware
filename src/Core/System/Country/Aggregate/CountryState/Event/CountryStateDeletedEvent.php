<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

class CountryStateDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country_state.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateDefinition::class;
    }
}
