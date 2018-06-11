<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;

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
