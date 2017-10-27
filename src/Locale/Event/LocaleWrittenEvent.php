<?php declare(strict_types=1);

namespace Shopware\Locale\Event;

use Shopware\Api\Write\WrittenEvent;

class LocaleWrittenEvent extends WrittenEvent
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
