<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\Mail;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Mail\Definition\MailDefinition;

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
