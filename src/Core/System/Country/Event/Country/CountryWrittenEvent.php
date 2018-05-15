<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\Country;

use Shopware\System\Country\Definition\CountryDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CountryWrittenEvent extends WrittenEvent
{
    public const NAME = 'country.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryDefinition::class;
    }
}
