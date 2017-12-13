<?php declare(strict_types=1);

namespace Shopware\Country\Event\CountryStateTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Country\Definition\CountryStateTranslationDefinition;

class CountryStateTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'country_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateTranslationDefinition::class;
    }
}
