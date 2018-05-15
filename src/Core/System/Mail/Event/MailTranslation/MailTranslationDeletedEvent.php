<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Mail\Definition\MailTranslationDefinition;

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
