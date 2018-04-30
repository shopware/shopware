<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\Mail;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Mail\Definition\MailDefinition;

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
