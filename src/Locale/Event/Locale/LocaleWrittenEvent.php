<?php declare(strict_types=1);

namespace Shopware\Locale\Event\Locale;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Locale\Definition\LocaleDefinition;

class LocaleWrittenEvent extends WrittenEvent
{
    const NAME = 'locale.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleDefinition::class;
    }
}
