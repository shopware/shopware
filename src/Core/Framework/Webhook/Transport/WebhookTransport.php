<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Transport;

use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\Outbox\OutboxInsert;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Persists webhook deliveries to the outbox. When `WEBHOOKS_REWORK` is active the outbox
 * doubles as the queue and `MySQLWebhookReceiver` drives consumption; when inactive the
 * envelope is additionally forwarded to the async transport for backward compatibility.
 *
 * @internal
 */
#[Package('framework')]
class WebhookTransport implements TransportInterface, KeepaliveReceiverInterface
{
    public function __construct(
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly TransportInterface $asyncTransport,
        private readonly MySQLWebhookReceiver $receiver,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            throw WebhookException::unsupportedMessage($message::class);
        }

        try {
            $this->webhookOutboxStore->recordOutboxEntry(OutboxInsert::fromMessage($message));
        } catch (DBALException $e) {
            /** @phpstan-ignore shopware.domainException (Symfony Messenger's worker contract requires TransportException for transport-layer failures.) */
            throw new TransportException($e->getMessage(), 0, $e);
        }

        if (!$this->outboxOwnsLifecycle()) {
            return $this->asyncTransport->send($envelope);
        }

        return $envelope;
    }

    public function get(): iterable
    {
        if (!$this->outboxOwnsLifecycle()) {
            return [];
        }

        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        if ($this->outboxOwnsLifecycle()) {
            $this->receiver->ack($envelope);
        }
    }

    public function reject(Envelope $envelope): void
    {
        if ($this->outboxOwnsLifecycle()) {
            $this->receiver->reject($envelope);
        }
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        if ($this->outboxOwnsLifecycle()) {
            $this->receiver->keepalive($envelope, $seconds);
        }
    }

    private function outboxOwnsLifecycle(): bool
    {
        return Feature::isActive('WEBHOOKS_REWORK');
    }
}
