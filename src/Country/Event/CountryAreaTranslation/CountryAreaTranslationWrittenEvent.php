<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryAreaTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Country\Definition\CountryAreaTranslationDefinition;

class CountryAreaTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'country_area_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryAreaTranslationDefinition::class;
    }
}
