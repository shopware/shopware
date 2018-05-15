<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\Mail;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Mail\Definition\MailDefinition;

class MailDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'mail.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailDefinition::class;
    }
}
