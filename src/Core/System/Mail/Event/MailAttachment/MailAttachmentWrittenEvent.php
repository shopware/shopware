<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailAttachment;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Mail\Definition\MailAttachmentDefinition;

class MailAttachmentWrittenEvent extends WrittenEvent
{
    public const NAME = 'mail_attachment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailAttachmentDefinition::class;
    }
}
