<?php declare(strict_types=1);

namespace Shopware\Locale\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class LocaleWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'locale.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'locale';
    }
}
