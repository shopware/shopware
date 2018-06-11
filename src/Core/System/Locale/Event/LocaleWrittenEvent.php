<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Locale\LocaleDefinition;

class LocaleWrittenEvent extends WrittenEvent
{
    public const NAME = 'locale.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleDefinition::class;
    }
}
