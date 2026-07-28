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
 * Constructor must not eagerly resolve `messenger.transport.*` services — Symfony's
 * `messenger.transport_factory` iteration would re-enter itself. Defer with
 * `service_closure` and resolve in `createTransport()`.
 *
 * @implements TransportFactoryInterface<WebhookTransport>
 */
#[Package('framework')]
class WebhookTransportFactory implements TransportFactoryInterface
{
    /**
     * @param \Closure(): TransportInterface $asyncTransportLocator
     * @param \Closure(): MySQLWebhookReceiver $receiverLocator
     */
    public function __construct(
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly \Closure $asyncTransportLocator,
        private readonly \Closure $receiverLocator,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new WebhookTransport(
            $this->webhookOutboxStore,
            ($this->asyncTransportLocator)(),
            ($this->receiverLocator)(),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return $dsn === 'shopware-webhook://default';
    }
}
