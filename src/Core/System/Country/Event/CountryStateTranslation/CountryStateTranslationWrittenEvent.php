<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryStateTranslation;

use Shopware\System\Country\Definition\CountryStateTranslationDefinition;
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
