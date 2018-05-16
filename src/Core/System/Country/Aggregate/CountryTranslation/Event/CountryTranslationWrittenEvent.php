<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CountryTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'country_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryTranslationDefinition::class;
    }
}
