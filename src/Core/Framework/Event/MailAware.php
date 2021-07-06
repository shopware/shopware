<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;

/**
 * @internal (FEATURE_NEXT_8225)
 */
interface MailAware extends ShopwareEvent
{
    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;

    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}
