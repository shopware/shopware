<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationDefinition;

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
