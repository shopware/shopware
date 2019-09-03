<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

interface MailActionInterface extends BusinessEventInterface
{
    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
