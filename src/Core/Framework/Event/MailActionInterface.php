<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_8225) tag:v6.5.0.0 - Will be removed in v6.5.0.0 Use MailAware instead
 */
interface MailActionInterface extends BusinessEventInterface
{
    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
