<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\Filter;

use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Storefront\Event\NewsletterEvents;

class NewsletterLogEntryFilter implements LogEntryFilterInterface
{
    public function getSupportedEvents(): array
    {
        return [NewsletterEvents::NEWSLETTER_REGISTER_CONFIRM_EVENT, NewsletterEvents::NEWSLETTER_REGISTER_EVENT];
    }

    public function parseEntry(): array
    {
        // TODO: Implement parseEntry() method.
        // do acl,  do enrichment
    }

    public function filterEventData(BusinessEventInterface $event): array
    {
        if (!$event instanceof NewsletterConfirmEvent) {
            return [];
        }

        return ['salesChannelId' => $event->getName()];
    }
}
