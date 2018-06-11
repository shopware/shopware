<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Locale\LocaleDefinition;

class LocaleDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'locale.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LocaleDefinition::class;
    }
}
