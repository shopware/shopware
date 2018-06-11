<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Mail\MailDefinition;

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
