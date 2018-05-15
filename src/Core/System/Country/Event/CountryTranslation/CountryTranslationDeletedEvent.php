<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryTranslation;

use Shopware\System\Country\Definition\CountryTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
