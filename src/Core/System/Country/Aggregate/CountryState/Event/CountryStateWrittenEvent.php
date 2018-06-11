<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

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
