<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

interface MailActionInterface
{
    public function getMailStruct(): MailRecipientStruct;
}
