<?php declare(strict_types=1);

namespace Shopware\Country\Event\Country;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Country\Definition\CountryDefinition;

class CountryWrittenEvent extends WrittenEvent
{
    const NAME = 'country.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryDefinition::class;
    }
}
