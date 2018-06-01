<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Mail\MailDefinition;

class MailWrittenEvent extends WrittenEvent
{
    public const NAME = 'mail.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return MailDefinition::class;
    }
}
