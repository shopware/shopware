<?php declare(strict_types=1);

namespace Shopware\System\Language\Event;

use Shopware\System\Language\LanguageDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class LanguageDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'language.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LanguageDefinition::class;
    }
}
