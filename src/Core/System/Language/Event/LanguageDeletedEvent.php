<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\Event;

use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
