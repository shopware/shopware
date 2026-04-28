<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Transport;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 *
 * @implements TransportFactoryInterface<WebhookTransport>
 */
#[Package('framework')]
class WebhookTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly TransportInterface $asyncTransport,
        private readonly MySQLWebhookReceiver $receiver,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new WebhookTransport($this->webhookOutboxStore, $this->asyncTransport, $this->receiver);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return $dsn === 'shopware-webhook://default';
    }
}
