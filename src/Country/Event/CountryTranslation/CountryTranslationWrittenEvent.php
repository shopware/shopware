<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Country\Definition\CountryTranslationDefinition;

class CountryTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'country_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryTranslationDefinition::class;
    }
}
