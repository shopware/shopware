<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class MailTranslationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'mail_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'mail_translation';
    }
}
