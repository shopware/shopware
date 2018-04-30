<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryStateTranslation;

use Shopware\Api\Country\Definition\CountryStateTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
