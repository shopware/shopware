<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class MailAttachmentWrittenEvent extends WrittenEvent
{
    const NAME = 'mail_attachment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'mail_attachment';
    }
}
