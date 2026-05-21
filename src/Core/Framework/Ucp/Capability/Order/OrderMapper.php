<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Order;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Translates a Shopware {@see OrderEntity} into the UCP order schema per
 * `ucp/docs/specification/order-rest.md`:
 *
 *   - `id`, `order_number`, `status`, `created_at`, `permalink_url`
 *   - `line_items[]` — with `quantity: { total, fulfilled }`, per-line `totals[]`
 *   - `totals[]` — order-level breakdown
 *   - `buyer`
 *   - `fulfillment` — with `expectations[]` and `events[]`
 *
 * @internal
 */
#[Package('framework')]
class OrderMapper
{
    /**
     * Public entry point used by both REST + MCP. `toUcpOrder()` is kept as an
     * alias for callers that historically used the older method name.
     *
     * @return array<string, mixed>
     */
    public function toResponse(OrderEntity $order, ?string $storefrontBaseUrl = null): array
    {
        $currency = $order->getCurrency()?->getIsoCode() ?? 'EUR';

        $lineItems = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $unitPrice = (int) round($lineItem->getUnitPrice() * 100);
            $lineTotalAmount = (int) round($lineItem->getTotalPrice() * 100);
            $price = $lineItem->getPrice();
            $taxAmount = $price !== null
                ? (int) round($price->getCalculatedTaxes()->getAmount() * 100)
                : 0;

            $lineItems[] = [
                'id' => $lineItem->getId(),
                'item' => [
                    'id' => (\is_string($lineItem->getPayload()['productNumber'] ?? null) && $lineItem->getPayload()['productNumber'] !== '')
                        ? $lineItem->getPayload()['productNumber']
                        : ($lineItem->getReferencedId() ?? $lineItem->getProductId() ?? $lineItem->getId()),
                    'title' => $lineItem->getLabel(),
                    'price' => $unitPrice,
                ],
                // Per order-rest.md the quantity is a structured object.
                'quantity' => [
                    'original' => $lineItem->getQuantity(),
                    'total' => $lineItem->getQuantity(),
                    'fulfilled' => 0,
                ],
                'price' => ['amount' => $unitPrice, 'currency' => $currency],
                'totals' => [
                    ['type' => 'subtotal', 'amount' => $lineTotalAmount, 'currency' => $currency],
                    ['type' => 'tax', 'amount' => $taxAmount, 'currency' => $currency],
                    ['type' => 'total', 'amount' => $lineTotalAmount, 'currency' => $currency],
                ],
                'status' => 'processing',
            ];
        }

        $permalink = $storefrontBaseUrl !== null ? rtrim($storefrontBaseUrl, '/') . '/account/order/' . $order->getId() : 'https://example.invalid/orders/' . $order->getId();
        $deepLink = $order->getDeepLinkCode();
        if ($storefrontBaseUrl !== null && \is_string($deepLink) && $deepLink !== '') {
            $permalink = rtrim($storefrontBaseUrl, '/') . '/account/order/' . $deepLink;
        }
        $customFields = $order->getCustomFields() ?? [];
        $checkoutId = \is_string($customFields['ucp_checkout_id'] ?? null) ? $customFields['ucp_checkout_id'] : $order->getId();

        return array_filter([
            'id' => $order->getId(),
            'checkout_id' => $checkoutId,
            'order_number' => $order->getOrderNumber(),
            'status' => $this->mapStatus($order),
            'created_at' => $order->getOrderDateTime()->format(\DateTimeInterface::ATOM),
            'permalink_url' => $permalink,
            'currency' => $currency,
            'line_items' => $lineItems,
            'totals' => [
                ['type' => 'subtotal', 'amount' => (int) round($order->getPositionPrice() * 100), 'currency' => $currency],
                ['type' => 'tax', 'amount' => (int) round($order->getPrice()->getCalculatedTaxes()->getAmount() * 100), 'currency' => $currency],
                ['type' => 'total', 'amount' => (int) round($order->getAmountTotal() * 100), 'currency' => $currency],
            ],
            'buyer' => $this->mapBuyer($order),
            'fulfillment' => $this->mapFulfillment($order),
            'adjustments' => [],
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * Legacy alias for {@see toResponse()}. Webhook publisher + MCP `get_order`
     * historically called this name.
     *
     * @return array<string, mixed>
     */
    public function toUcpOrder(OrderEntity $order, ?string $storefrontBaseUrl = null): array
    {
        return $this->toResponse($order, $storefrontBaseUrl);
    }

    private function mapStatus(OrderEntity $order): string
    {
        $state = $order->getStateMachineState()?->getTechnicalName();

        // Map Shopware order-state machine names to UCP status enum
        // (spec: open | in_progress | completed | canceled | refunded).
        return match ($state) {
            'completed' => 'completed',
            'cancelled', 'canceled' => 'canceled',
            'in_progress' => 'in_progress',
            'refunded' => 'refunded',
            'open' => 'open',
            // Anything we don't explicitly know (custom states added by plugins) is reported
            // verbatim — the order-rest enum allows extension values.
            null => 'open',
            default => $state,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBuyer(OrderEntity $order): array
    {
        $customer = $order->getOrderCustomer();

        return [
            'email' => $customer?->getEmail(),
            'first_name' => $customer?->getFirstName(),
            'last_name' => $customer?->getLastName(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFulfillment(OrderEntity $order): array
    {
        $expectations = [];
        $events = [];
        $customFields = $order->getCustomFields() ?? [];
        $storedFulfillment = \is_array($customFields['ucp_fulfillment_json'] ?? null) ? $customFields['ucp_fulfillment_json'] : null;
        $storedDestination = $storedFulfillment['methods'][0]['destinations'][0] ?? ['address_country' => ''];

        foreach ($order->getDeliveries() ?? [] as $delivery) {
            $expectations[] = [
                'id' => $delivery->getId(),
                'line_items' => array_values(array_map(
                    static fn ($lineItem): array => ['id' => $lineItem->getId(), 'quantity' => $lineItem->getQuantity()],
                    iterator_to_array($order->getLineItems() ?? new \ArrayIterator())
                )),
                'method_type' => 'shipping',
                'fulfillable_on' => $delivery->getShippingDateEarliest()->format(\DateTimeInterface::ATOM),
                'arrives_by' => $delivery->getShippingDateLatest()->format(\DateTimeInterface::ATOM),
                'destination' => \is_array($storedDestination) ? $storedDestination : [],
            ];

            $stateName = $delivery->getStateMachineState()?->getTechnicalName();
            if ($stateName === 'shipped') {
                $occurredAt = $delivery->getUpdatedAt() ?? $delivery->getCreatedAt();
                $events[] = [
                    'id' => $delivery->getId() . '_shipped',
                    'type' => 'shipped',
                    'occurred_at' => $occurredAt?->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        return [
            'expectations' => $expectations,
            'events' => $events,
        ];
    }
}
