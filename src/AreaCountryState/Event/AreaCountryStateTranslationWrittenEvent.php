<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class AreaCountryStateTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'area_country_state_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'area_country_state_translation';
    }
}
