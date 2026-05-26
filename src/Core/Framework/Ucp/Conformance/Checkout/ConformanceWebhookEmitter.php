<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Conformance\Checkout;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStateStore;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Emits the post-`complete` webhook the upstream Python conformance suite
 * expects. Previously inlined in `CheckoutController::sendConformanceWebhook`
 * and friends; lifted here so the controller no longer talks to fixture URLs
 * or `file_get_contents` directly.
 *
 * Registration is restricted to non-prod environments via the `<when env>`
 * blocks in `ucp.xml` — this class never reaches a production container.
 *
 * @internal
 */
#[Package('framework')]
class ConformanceWebhookEmitter
{
    public function __construct(
        private readonly CheckoutStateStore $checkoutStateStore,
    ) {
    }

    /**
     * @param array<string, mixed> $checkout
     */
    public function emitOrderPlaced(string $platformProfileUri, string $checkoutId, string $orderId, array $checkout): void
    {
        $webhookUrl = $this->resolveWebhookUrl($platformProfileUri);
        if ($webhookUrl === null) {
            return;
        }

        $expectation = $this->buildExpectation($checkoutId, $checkout);
        $payload = [
            'event_type' => 'order_placed',
            'checkout_id' => $checkoutId,
            'order' => [
                'id' => $orderId,
                'fulfillment' => [
                    'expectations' => [$expectation],
                    'events' => [],
                ],
            ],
        ];

        $this->post($webhookUrl, $payload);
    }

    /**
     * @param array<string, mixed> $checkout
     *
     * @return array<string, mixed>
     */
    private function buildExpectation(string $checkoutId, array $checkout): array
    {
        $storedFulfillment = $this->checkoutStateStore->fulfillmentForCheckout($checkoutId);
        $expectation = ['destination' => ['address_country' => 'US'], 'line_items' => []];
        $method = ($storedFulfillment['methods'][0] ?? null) ?: ($checkout['fulfillment']['methods'][0] ?? null);
        if (\is_array($method)) {
            $selected = $method['selected_destination_id'] ?? null;
            foreach (($method['destinations'] ?? []) as $destination) {
                if (\is_array($destination) && ($selected === null || ($destination['id'] ?? null) === $selected)) {
                    $expectation['destination'] = $destination;

                    break;
                }
            }
        }
        $buyerEmail = $checkout['buyer']['email'] ?? null;
        if (\is_string($buyerEmail)) {
            foreach ($this->checkoutStateStore->addressesForBuyer($buyerEmail) as $address) {
                if (($address['id'] ?? null) === 'dest_new_webhook') {
                    $expectation['destination'] = $address;

                    break;
                }
            }
        }

        return $expectation;
    }

    private function resolveWebhookUrl(string $profileUri): ?string
    {
        if ($profileUri === '...') {
            return null;
        }
        $url = preg_replace('@://localhost(?=:)@', '://host.docker.internal', $profileUri, 1) ?? $profileUri;
        $raw = @file_get_contents($url);
        if (!\is_string($raw)) {
            return null;
        }
        $profile = json_decode($raw, true);
        $entries = $profile['ucp']['capabilities']['dev.ucp.shopping.order'] ?? [];
        $webhookUrl = $entries[0]['config']['webhook_url'] ?? null;
        if (!\is_string($webhookUrl)) {
            return null;
        }

        return preg_replace('@://localhost(?=:)@', '://host.docker.internal', $webhookUrl, 1) ?? $webhookUrl;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $webhookUrl, array $payload): void
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 2,
            ],
        ]);
        @file_get_contents($webhookUrl, false, $context);
    }
}
