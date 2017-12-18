<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\MailTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Mail\Definition\MailTranslationDefinition;

class MailTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'mail_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailTranslationDefinition::class;
    }
}
