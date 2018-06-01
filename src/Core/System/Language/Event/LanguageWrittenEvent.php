<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
