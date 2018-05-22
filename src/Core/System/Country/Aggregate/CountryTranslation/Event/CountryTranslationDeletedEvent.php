<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryTranslation\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;

class CountryTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'country_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CountryTranslationDefinition::class;
    }
}
