<?php declare(strict_types=1);

namespace Shopware\System\Language\Event;

use Shopware\System\Language\LanguageDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
