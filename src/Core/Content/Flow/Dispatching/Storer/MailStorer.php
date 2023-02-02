<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;

class MailStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MailAware) {
            return $stored;
        }

        if (!isset($stored[MailAware::MAIL_STRUCT])) {
            try {
                $mailStruct = $event->getMailStruct();
                $data = [
                    'recipients' => $mailStruct->getRecipients(),
                    'bcc' => $mailStruct->getBcc(),
                    'cc' => $mailStruct->getCc(),
                ];

                $stored[MailAware::MAIL_STRUCT] = $data;
            } catch (MailEventConfigurationException $e) {
            }
        }

        if (isset($stored[MailAware::SALES_CHANNEL_ID])) {
            return $stored;
        }

        $stored[MailAware::SALES_CHANNEL_ID] = $event->getSalesChannelId() ?? Defaults::SALES_CHANNEL;

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MailAware::MAIL_STRUCT)) {
            return;
        }

        $mailStructData = $storable->getStore(MailAware::MAIL_STRUCT);

        $mailStruct = new MailRecipientStruct($mailStructData['recipients'] ?? []);
        $mailStruct->setBcc($mailStructData['bcc'] ?? null);
        $mailStruct->setCc($mailStructData['cc'] ?? null);

        $storable->setData(MailAware::MAIL_STRUCT, $mailStruct);
    }
}
