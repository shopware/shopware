<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - extends of FlowEventAware will be removed, implement the interface inside your event
 */
#[Package('business-ops')]
interface MailAware extends FlowEventAware
{
    public const MAIL_STRUCT = 'mailStruct';

    public const SALES_CHANNEL_ID = 'salesChannelId';

    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
