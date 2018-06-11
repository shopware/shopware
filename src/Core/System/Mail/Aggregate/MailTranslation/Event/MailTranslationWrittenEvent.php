<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailTranslation\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition;

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
