<?php declare(strict_types=1);

namespace Shopware\Unit\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class UnitTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'unit_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'unit_translation';
    }
}
