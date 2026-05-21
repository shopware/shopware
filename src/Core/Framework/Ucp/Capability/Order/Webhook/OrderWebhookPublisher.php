<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Order\Webhook;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderMapper;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpNegotiationSessionCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpNegotiationSessionEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\Profile\PlatformProfileFetcher;
use Shopware\Core\Framework\Ucp\Profile\UrlSafetyValidator;
use Shopware\Core\Framework\Ucp\Transport\Signature\Rfc9421SignatureBuilder;
use Shopware\Core\Framework\Ucp\UcpEvents;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Emits signed UCP order webhooks to every platform with an active
 * negotiation session that has subscribed to `dev.ucp.shopping.order`.
 *
 * Signing follows the same RFC 9421 mechanism as the inbound verifier —
 * outbound headers include `Content-Digest`, `Signature-Input`, and
 * `Signature`, signed with the active Sales Channel key.
 *
 * @internal
 */
#[Package('framework')]
class OrderWebhookPublisher
{
    /**
     * @param EntityRepository<UcpNegotiationSessionCollection> $negotiationSessionRepository
     */
    public function __construct(
        private readonly EntityRepository $negotiationSessionRepository,
        private readonly UcpSigningKeyProvider $signingKeyProvider,
        private readonly OrderMapper $orderMapper,
        private readonly Rfc9421SignatureBuilder $signatureBuilder,
        private readonly PlatformProfileFetcher $platformProfileFetcher,
        private readonly UrlSafetyValidator $urlSafetyValidator,
        private readonly HttpClientInterface $httpClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly string $environment = 'prod',
    ) {
    }

    public function publish(OrderEntity $order, Context $context): void
    {
        $salesChannelId = $order->getSalesChannelId();
        $sessions = $this->negotiationSessionRepository->search(
            (new Criteria())->addFilter(new EqualsFilter(
                'salesChannelId',
                $salesChannelId
            )),
            $context
        );

        if ($sessions->count() === 0) {
            return;
        }

        $signingKey = $this->signingKeyProvider->getActive($salesChannelId, $context);
        if ($signingKey === null) {
            $this->logger->warning('UCP: no active signing key for sales channel', ['sales_channel_id' => $salesChannelId]);

            return;
        }
        $privateKeyPem = $this->signingKeyProvider->getPrivateKeyPem($signingKey);

        foreach ($sessions as $session) {
            \assert($session instanceof UcpNegotiationSessionEntity);
            // Per-session envelope so the platform sees the exact negotiated
            // capability set with which the order was placed (overview.md
            // §"Response Envelope" — webhooks follow the same envelope rules).
            $activeCapabilities = $session->getActiveCapabilities();
            $eventId = bin2hex(random_bytes(16));
            $createdAt = gmdate('c');
            $envelope = [
                'version' => $session->getProtocolVersion(),
                'event' => 'order.updated',
                'event_id' => $eventId,
                'created_at' => $createdAt,
            ];
            if ($activeCapabilities !== []) {
                $envelope['capabilities'] = $this->filterCapabilitiesForOrder($activeCapabilities);
            }

            if (!isset($activeCapabilities['dev.ucp.shopping.order'])) {
                continue;
            }

            $webhookUrl = $this->resolveWebhookUrl($session, $context);
            if ($webhookUrl === null) {
                continue;
            }

            $payload = [
                'ucp' => $envelope,
                'order' => $this->orderMapper->toUcpOrder($order),
            ];
            $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

            try {
                $signed = $this->signatureBuilder->signRequest(
                    method: 'POST',
                    targetUri: $webhookUrl,
                    body: $body,
                    headers: [
                        'content-type' => 'application/json',
                        'webhook-id' => $eventId,
                        'webhook-timestamp' => (string) time(),
                    ],
                    key: $signingKey,
                    privateKeyPem: $privateKeyPem
                );

                $this->httpClient->request('POST', $webhookUrl, [
                    'body' => $body,
                    'headers' => $signed['headers'],
                    'timeout' => 10,
                    'max_redirects' => 0,
                ]);

                $this->eventDispatcher->dispatch(
                    new class($order, $webhookUrl) extends Event {
                        public function __construct(
                            public readonly OrderEntity $order,
                            public readonly string $webhookUrl,
                        ) {
                        }
                    },
                    UcpEvents::ORDER_WEBHOOK_DISPATCHED
                );
            } catch (\Throwable $e) {
                $this->logger->warning('UCP order webhook dispatch failed', [
                    'webhook_url' => $webhookUrl,
                    'order_id' => $order->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Webhook envelopes only carry the order-relevant capabilities. The full
     * negotiated intersection is unnecessary noise on a notification event,
     * and may also leak information about other extensions the platform did
     * not opt into for this domain.
     *
     * @param array<string, mixed> $negotiated
     *
     * @return array<string, mixed>
     */
    private function filterCapabilitiesForOrder(array $negotiated): array
    {
        $allowed = ['dev.ucp.shopping.order', 'dev.ucp.shopping.ap2_mandate'];
        $filtered = [];
        foreach ($negotiated as $name => $entries) {
            if (\in_array($name, $allowed, true)) {
                $filtered[$name] = $entries;
            }
        }

        return $filtered;
    }

    private function resolveWebhookUrl(UcpNegotiationSessionEntity $session, Context $context): ?string
    {
        try {
            $profile = $this->platformProfileFetcher->fetch($session->getPlatformProfileUri(), $context);
        } catch (\Throwable $e) {
            $this->logger->warning('UCP: cannot resolve platform profile for webhook', [
                'profile_uri' => $session->getPlatformProfileUri(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $ucp = $profile['ucp'] ?? [];
        $capabilities = $ucp['capabilities'] ?? [];
        $orderEntries = $capabilities['dev.ucp.shopping.order'] ?? [];
        if (!\is_array($orderEntries) || $orderEntries === []) {
            return null;
        }

        foreach ($orderEntries as $entry) {
            $config = $entry['config'] ?? [];
            if (\is_array($config) && isset($config['webhook_url']) && \is_string($config['webhook_url'])) {
                $this->urlSafetyValidator->validateAndResolve($config['webhook_url'], null, $this->environment);

                return $config['webhook_url'];
            }
        }

        return null;
    }
}
