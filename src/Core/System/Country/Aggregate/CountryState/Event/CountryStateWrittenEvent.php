<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Event;

use Shopware\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CountryStateWrittenEvent extends WrittenEvent
{
    public const NAME = 'country_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateDefinition::class;
    }
}
