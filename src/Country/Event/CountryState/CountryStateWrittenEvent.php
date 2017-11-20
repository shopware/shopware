<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryState;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Country\Definition\CountryStateDefinition;

class CountryStateWrittenEvent extends WrittenEvent
{
    const NAME = 'country_state.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateDefinition::class;
    }
}
