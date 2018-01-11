<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\MailAttachment;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Mail\Definition\MailAttachmentDefinition;

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
