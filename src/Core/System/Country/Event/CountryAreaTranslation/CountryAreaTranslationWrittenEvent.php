<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryAreaTranslation;

use Shopware\System\Country\Definition\CountryAreaTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class CountryAreaTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'country_area_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryAreaTranslationDefinition::class;
    }
}
