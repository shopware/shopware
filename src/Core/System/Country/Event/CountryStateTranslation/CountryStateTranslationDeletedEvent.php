<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryStateTranslation;

use Shopware\System\Country\Definition\CountryStateTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CountryStateTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country_state_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryStateTranslationDefinition::class;
    }
}
