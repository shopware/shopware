<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Transport;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxEventRepository;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Dedicated Messenger transport for webhook delivery.
 *
 * Send path: persists the outbox entry (webhook_event_log + webhook_delivery),
 * then forwards to the configured async transport for worker consumption.
 *
 * Receive path: will be implemented in a follow-up PR with stream-leased
 * MySQLWebhookReceiver — the outbox becomes the queue itself and workers
 * consume directly from `messenger:consume webhook`.
 *
 * @internal
 */
#[Package('framework')]
class WebhookTransport implements TransportInterface
{
    public function __construct(
        private readonly OutboxEventRepository $outboxEventRepository,
        private readonly TransportInterface $asyncTransport,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            throw WebhookException::unsupportedMessage($message::class);
        }

        $this->outboxEventRepository->ensureOutboxEntry(new OutboxInsert(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        ));

        // Forward to the configured async transport for worker consumption.
        return $this->asyncTransport->send($envelope);
    }

    /**
     * @return list<Envelope>
     */
    public function get(): iterable
    {
        // TODO: Implement stream-leased MySQLWebhookReceiver in a follow-up PR.
        // This will poll webhook_delivery for due PENDING_RETRY rows and return
        // them as Envelopes for `messenger:consume webhook`.
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}
