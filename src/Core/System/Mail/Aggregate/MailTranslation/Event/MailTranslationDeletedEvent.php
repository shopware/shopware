<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition;

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
