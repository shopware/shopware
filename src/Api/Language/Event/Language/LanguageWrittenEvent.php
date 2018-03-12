<?php declare(strict_types=1);

namespace Shopware\Api\Language\Event\Language;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Language\Definition\LanguageDefinition;

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
