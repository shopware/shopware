<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

/**
 * @internal (flag:FEATURE_NEXT_8225)
 */
interface MailAware extends FlowEventAware
{
    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
