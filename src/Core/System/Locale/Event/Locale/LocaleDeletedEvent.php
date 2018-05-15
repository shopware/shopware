<?php declare(strict_types=1);

namespace Shopware\System\Locale\Event\Locale;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Locale\Definition\LocaleDefinition;

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
