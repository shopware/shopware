<?php declare(strict_types=1);

namespace Shopware\Mail\Event\MailAttachment;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Mail\Definition\MailAttachmentDefinition;

class MailAttachmentWrittenEvent extends WrittenEvent
{
    const NAME = 'mail_attachment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailAttachmentDefinition::class;
    }
}
