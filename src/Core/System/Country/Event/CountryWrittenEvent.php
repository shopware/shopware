<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Country\CountryDefinition;

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
