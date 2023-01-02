<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0 Use MailAware instead
 * @package business-ops
 */
#[Package('business-ops')]
interface MailActionInterface extends BusinessEventInterface
{
    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
