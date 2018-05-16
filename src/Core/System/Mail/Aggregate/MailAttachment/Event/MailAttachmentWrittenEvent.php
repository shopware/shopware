<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailAttachment\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Mail\Aggregate\MailAttachment\MailAttachmentDefinition;

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
