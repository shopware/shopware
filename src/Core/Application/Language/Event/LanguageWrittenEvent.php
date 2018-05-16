<?php declare(strict_types=1);

namespace Shopware\Application\Language\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Application\Language\LanguageDefinition;

class LanguageWrittenEvent extends WrittenEvent
{
    public const NAME = 'language.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LanguageDefinition::class;
    }
}
