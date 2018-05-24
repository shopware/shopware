<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryStateTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;

class CountryStateTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'country_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateTranslationDefinition::class;
    }
}
