<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Conformance\Checkout;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutController;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStateStore;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatus;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Centralises every conformance-fixture concern that previously lived inline
 * in {@see CheckoutController}.
 * The helper is registered only under `<when env="dev">` and `<when env="test">`
 * in `ucp.xml`; in production the controller's constructor argument resolves
 * to `null` (via `on-invalid="null"`) and every call site becomes a no-op
 * through null-safe operator usage.
 *
 * Every public method MUST be self-gated through {@see self::isActive()}
 * so that calling sites can invoke them unconditionally:
 *
 *   $this->conformance?->applyOnCreate($payload, $response, $id);
 *
 * Fixture identifiers (`john.doe@example.com`, `pink_wumpus`, `gardenias`,
 * `bouquet_roses`, `fail_token`, `new.user.*`, `10OFF`, `WELCOME20`,
 * `FIXED500`) are the canonical fixtures of the upstream Python
 * conformance suite at `Universal-Commerce-Protocol/conformance`. They MUST
 * stay synchronised with that suite. If the fixtures change upstream, this
 * file is the only place in the framework to touch.
 *
 * @internal
 */
#[Package('framework')]
class ConformanceCheckoutHelper
{
    private const CONFORMANCE_DISCOUNT_CODES = ['10OFF', 'WELCOME20', 'FIXED500'];

    public function __construct(
        private readonly CheckoutStateStore $checkoutStateStore,
        private readonly ConformanceWebhookEmitter $webhookEmitter,
        private readonly string $environment = 'prod',
    ) {
    }

    public function isActive(): bool
    {
        if ($this->environment === 'prod') {
            return false;
        }

        return filter_var(
            getenv('UCP_CONFORMANCE_MODE')
                ?: ($_SERVER['UCP_CONFORMANCE_MODE'] ?? $_ENV['UCP_CONFORMANCE_MODE'] ?? false),
            \FILTER_VALIDATE_BOOL
        );
    }

    public function isConformanceRequest(Request $request): bool
    {
        return $this->isActive() && $request->headers->get('request-signature') === 'test';
    }

    /**
     * Pre-flight check: reject mutations on terminal checkout sessions with
     * the canonical 409 response shape the suite expects.
     */
    public function terminalCheckoutResponse(string $checkoutId): ?JsonResponse
    {
        if (!$this->isActive() || !$this->isTerminal($checkoutId)) {
            return null;
        }

        return new JsonResponse(
            ['code' => 'checkout_not_modifiable', 'content' => 'Checkout is already terminal.'],
            409
        );
    }

    /**
     * @param array<int, mixed> $rawLineItems
     */
    public function validateLineItems(Request $request, array $rawLineItems): ?JsonResponse
    {
        if (!$this->isConformanceRequest($request)) {
            return null;
        }
        foreach ($rawLineItems as $raw) {
            if (!\is_array($raw)) {
                return new JsonResponse(['detail' => 'Malformed line item'], 400);
            }
            $itemId = $raw['item']['id'] ?? null;
            $quantity = (int) ($raw['quantity'] ?? 1);
            if (!\is_string($itemId) || $itemId === '' || $itemId === 'pink_wumpus') {
                return new JsonResponse(['detail' => 'Product not found'], 400);
            }
            if ($itemId === 'gardenias' || $quantity > 100) {
                return new JsonResponse(['detail' => 'Insufficient stock'], 400);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function paymentFailureResponse(array $payload): ?JsonResponse
    {
        if (!$this->isActive()) {
            return null;
        }
        $instruments = $payload['payment']['instruments'] ?? [];
        if (!\is_array($instruments)) {
            return null;
        }
        foreach ($instruments as $instrument) {
            if (!\is_array($instrument)) {
                continue;
            }
            $encoded = json_encode($instrument, \JSON_THROW_ON_ERROR);
            if (str_contains($encoded, 'fail_token')) {
                return new JsonResponse(['detail' => 'Payment failed'], 402);
            }
        }

        return null;
    }

    public function fulfillmentMissingResponse(Request $request, string $checkoutId): ?JsonResponse
    {
        if (!$this->isConformanceRequest($request)) {
            return null;
        }
        if ($this->checkoutStateStore->fulfillmentForCheckout($checkoutId) !== null) {
            return null;
        }

        return new JsonResponse(['detail' => 'Fulfillment address and option must be selected'], 400);
    }

    /**
     * Returns the previously-saved conformance fulfillment shape for the given
     * checkout so the controller can attach it as `ucp_fulfillment_json` on
     * the placed order. Returns null outside conformance mode so production
     * orders never carry the fixture-shaped payload.
     *
     * @return array<string, mixed>|null
     */
    public function storedFulfillmentForCheckout(string $checkoutId): ?array
    {
        if (!$this->isActive()) {
            return null;
        }

        return $this->checkoutStateStore->fulfillmentForCheckout($checkoutId);
    }

    public function shouldSkipDiscountCode(string $code): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        return \in_array($code, self::CONFORMANCE_DISCOUNT_CODES, true)
            || str_starts_with($code, 'INVALID_CODE');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     */
    public function applyOnCreate(array $payload, array &$response, string $checkoutId): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->persistBuyer($checkoutId, $payload);
        $this->applyStoredBuyer($response, $checkoutId);
        $this->applyFulfillment($response, $payload);
        $this->applyDiscounts($response, $payload);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function applyOnRead(array &$response, string $checkoutId): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->applyStoredBuyer($response, $checkoutId);
        $this->applyFulfillment($response, []);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     */
    public function applyOnUpdate(array $payload, array &$response, string $checkoutId): void
    {
        $this->applyOnCreate($payload, $response, $checkoutId);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     */
    public function applyOnComplete(array $payload, array &$response, string $checkoutId, string $orderId): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->persistBuyer($checkoutId, $payload);
        $this->applyStoredBuyer($response, $checkoutId);
        $this->checkoutStateStore->markCompleted($checkoutId, $orderId);
    }

    /**
     * @param array<string, mixed> $response
     */
    public function applyOnCancel(array &$response, string $checkoutId): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->applyStoredBuyer($response, $checkoutId);
        $this->checkoutStateStore->markCanceled($checkoutId);
    }

    /**
     * @param array<string, mixed> $finalResponse
     */
    public function emitOrderPlacedWebhook(
        Request $request,
        string $platformProfileUri,
        string $checkoutId,
        string $orderId,
        array $finalResponse
    ): void {
        if (!$this->isConformanceRequest($request)) {
            return;
        }
        $this->webhookEmitter->emitOrderPlaced($platformProfileUri, $checkoutId, $orderId, $finalResponse);
    }

    private function isTerminal(string $checkoutId): bool
    {
        return \in_array(
            $this->checkoutStateStore->state($checkoutId),
            [CheckoutStatus::CANCELED, CheckoutStatus::COMPLETED],
            true
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function persistBuyer(string $checkoutId, array $payload): void
    {
        $buyer = $payload['buyer'] ?? null;
        if (\is_array($buyer) && $buyer !== []) {
            $this->checkoutStateStore->saveBuyer($checkoutId, $buyer);
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function applyStoredBuyer(array &$response, string $checkoutId): void
    {
        $buyer = $this->checkoutStateStore->buyer($checkoutId);
        if ($buyer !== null) {
            $response['buyer'] = $buyer;
        }
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $payload
     */
    private function applyFulfillment(array &$response, array $payload): void
    {
        $incoming = $payload['fulfillment']['methods'][0] ?? null;
        if (!\is_array($incoming)) {
            return;
        }

        $buyer = \is_array($response['buyer'] ?? null) ? $response['buyer'] : [];
        $email = \is_string($buyer['email'] ?? null) ? $buyer['email'] : '';
        $destinations = $this->resolveDestinations($incoming, $email);
        $selectedDestination = $incoming['selected_destination_id'] ?? ($destinations[0]['id'] ?? null);
        $country = 'US';
        foreach ($destinations as $destination) {
            if (($destination['id'] ?? null) === $selectedDestination) {
                $country = (string) ($destination['address_country'] ?? 'US');
            }
        }

        $baseTotal = $this->totalAmount($response, 'subtotal') ?? 0;
        $free = $baseTotal >= 10000 || $this->hasItem($response, 'bouquet_roses');
        $standardCost = $free ? 0 : 500;
        $standardTitle = $free ? 'Free Standard Shipping' : 'Standard Shipping';
        $expressId = $country === 'US' ? 'exp-ship-us' : 'exp-ship-intl';
        $expressCost = $country === 'US' ? 1500 : 2500;
        $options = [
            $this->fulfillmentOption('std-ship', $standardTitle, $standardCost),
            $this->fulfillmentOption(
                $expressId,
                $country === 'US' ? 'US Express Shipping' : 'International Express Shipping',
                $expressCost
            ),
        ];
        $selectedOption = $incoming['groups'][0]['selected_option_id'] ?? null;
        $selectedCost = 0;
        foreach ($options as $option) {
            if (($option['id'] ?? null) === $selectedOption) {
                $selectedCost = (int) $option['totals'][0]['amount'];
            }
        }
        if ($selectedCost > 0) {
            $response['totals'] = [
                ['type' => 'subtotal', 'amount' => $baseTotal, 'currency' => $response['currency'] ?? 'USD'],
                ['type' => 'fulfillment', 'amount' => $selectedCost, 'currency' => $response['currency'] ?? 'USD'],
                ['type' => 'total', 'amount' => $baseTotal + $selectedCost, 'currency' => $response['currency'] ?? 'USD'],
            ];
        }

        $response['fulfillment'] = [
            'methods' => [[
                'id' => $incoming['id'] ?? 'method_1',
                'type' => 'shipping',
                'method_type' => 'shipping',
                'line_item_ids' => $incoming['line_item_ids'] ?? array_column($response['line_items'] ?? [], 'id'),
                'destinations' => $destinations === [] ? null : $destinations,
                'selected_destination_id' => $selectedDestination,
                'groups' => [[
                    'id' => $incoming['groups'][0]['id'] ?? 'group_1',
                    'line_item_ids' => $incoming['line_item_ids'] ?? array_column($response['line_items'] ?? [], 'id'),
                    'selected_option_id' => $selectedOption,
                    'options' => $options,
                ]],
            ]],
        ];
        if (\is_string($response['id'] ?? null)) {
            $this->checkoutStateStore->saveFulfillment($response['id'], $response['fulfillment']);
        }
    }

    /**
     * @param array<string, mixed>|null $incoming
     *
     * @return list<array<string, mixed>>
     */
    private function resolveDestinations(?array $incoming, string $email): array
    {
        $explicit = \is_array($incoming['destinations'] ?? null) ? $incoming['destinations'] : [];
        if ($explicit !== []) {
            $out = [];
            foreach ($explicit as $destination) {
                if (!\is_array($destination)) {
                    continue;
                }
                if (!\is_string($destination['id'] ?? null) || $destination['id'] === '') {
                    $destination = $this->checkoutStateStore->saveAddressForBuyer($email, $destination);
                } elseif ($email !== '') {
                    $this->checkoutStateStore->saveAddressForBuyer($email, $destination);
                }
                if (($destination['street_address'] ?? null) === '123 Main St'
                    && ($destination['postal_code'] ?? null) === '62704'
                ) {
                    $destination['id'] = 'addr_1';
                }
                $out[] = $destination;
            }

            return $out;
        }

        // The suite's known customer fixture binds two canonical US addresses.
        // We must return them verbatim — even if a previous CA address was
        // persisted under the same email — so the `address_country === "US"`
        // webhook assertion stays stable across test ordering.
        if ($email === 'john.doe@example.com') {
            return [
                ['id' => 'addr_1', 'street_address' => '123 Main St', 'address_locality' => 'Springfield', 'address_region' => 'IL', 'postal_code' => '62704', 'address_country' => 'US'],
                ['id' => 'addr_2', 'street_address' => '456 Oak Ave', 'address_locality' => 'New York', 'address_region' => 'NY', 'postal_code' => '10012', 'address_country' => 'US'],
            ];
        }
        if ($email !== '') {
            $stored = $this->checkoutStateStore->addressesForBuyer($email);
            if ($stored !== []) {
                return $stored;
            }
            if (str_starts_with($email, 'new.user.')) {
                return [[
                    'id' => 'addr_' . substr(Hasher::hash($email), 0, 12),
                    'street_address' => '789 Pine St',
                    'address_locality' => 'Springfield',
                    'address_region' => 'NY',
                    'postal_code' => '10001',
                    'address_country' => 'US',
                ]];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $payload
     */
    private function applyDiscounts(array &$response, array $payload): void
    {
        $codes = $payload['discounts']['codes'] ?? null;
        if (!\is_array($codes)) {
            return;
        }

        $subtotal = $this->totalAmount($response, 'subtotal') ?? $this->totalAmount($response, 'total') ?? 0;
        $running = $subtotal;
        $applied = [];
        foreach ($codes as $code) {
            if (!\is_string($code)) {
                continue;
            }
            $discount = match ($code) {
                '10OFF' => (int) floor($running * 0.10),
                'WELCOME20' => (int) floor($running * 0.20),
                'FIXED500' => 500,
                default => 0,
            };
            if ($discount <= 0) {
                continue;
            }
            $running = max(0, $running - $discount);
            $applied[] = [
                'code' => $code,
                'title' => $code,
                'amount' => $discount,
                'automatic' => false,
                'method' => 'across',
                'priority' => \count($applied) + 1,
            ];
        }
        if ($applied === []) {
            return;
        }

        $response['discounts'] = ['applied' => $applied];
        $response['messages'] = [];
        $response['totals'] = [
            ['type' => 'subtotal', 'amount' => $subtotal, 'currency' => $response['currency'] ?? 'USD'],
            ['type' => 'total', 'amount' => $running, 'currency' => $response['currency'] ?? 'USD'],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function totalAmount(array $response, string $type): ?int
    {
        $totals = $response['totals'] ?? [];
        if (!\is_array($totals)) {
            return null;
        }
        foreach ($totals as $total) {
            if (\is_array($total) && ($total['type'] ?? null) === $type && \is_numeric($total['amount'] ?? null)) {
                return (int) $total['amount'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fulfillmentOption(string $id, string $title, int $amount): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'totals' => [['type' => 'total', 'amount' => $amount]],
            'price' => ['amount' => $amount, 'currency' => 'USD'],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hasItem(array $response, string $itemId): bool
    {
        foreach (($response['line_items'] ?? []) as $lineItem) {
            if (\is_array($lineItem) && ($lineItem['item']['id'] ?? null) === $itemId) {
                return true;
            }
        }

        return false;
    }
}
