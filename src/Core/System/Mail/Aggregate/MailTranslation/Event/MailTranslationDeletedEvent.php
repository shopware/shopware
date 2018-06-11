<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition;

class MailTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'mail_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailTranslationDefinition::class;
    }
}
