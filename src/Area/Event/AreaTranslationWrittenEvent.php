<?php declare(strict_types=1);

namespace Shopware\Area\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class AreaTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'area_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'area_translation';
    }
}
