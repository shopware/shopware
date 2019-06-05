<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging\Filter;

use Shopware\Core\Content\MailTemplate\Service\Event\MailDispatchedEvent;
use Shopware\Core\Framework\Event\BusinessEventInterface;

class MailDispatchedLogEntryFilter implements LogEntryFilterInterface
{
    public function getSupportedEvents(): array
    {
        return [MailDispatchedEvent::EVENT_NAME];
    }

    public function parseEntry(): array
    {
        // TODO: Implement parseEntry() method.
        // do acl,  do enrichment
    }

    public function filterEventData(BusinessEventInterface $event): array
    {
        if (!$event instanceof MailDispatchedEvent) {
            return [];
        }

        return [
            'subject' => $event->getSubject(),
            'recipients' => $event->getRecipients(),
            'contents' => $event->getContents(),
        ];
    }
}
