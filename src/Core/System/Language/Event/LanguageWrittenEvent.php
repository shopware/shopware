<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Language\LanguageDefinition;

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
