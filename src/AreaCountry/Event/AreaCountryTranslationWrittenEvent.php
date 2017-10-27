<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Event;

use Shopware\Api\Write\WrittenEvent;

class AreaCountryTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'area_country_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'area_country_translation';
    }
}
