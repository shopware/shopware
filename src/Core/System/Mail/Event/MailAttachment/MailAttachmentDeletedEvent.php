<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailAttachment;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Mail\Definition\MailAttachmentDefinition;

class MailAttachmentDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'mail_attachment.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailAttachmentDefinition::class;
    }
}
