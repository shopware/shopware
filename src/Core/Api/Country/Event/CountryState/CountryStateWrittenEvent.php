<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryState;

use Shopware\Api\Country\Definition\CountryStateDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
