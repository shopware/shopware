<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Discount;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Translates Shopware promotion line items into the UCP `discounts.applied[]`
 * shape per `ucp/docs/specification/discount.md`.
 *
 * A discount entry has the following UCP-defined fields:
 *
 *   - `id`         — opaque, stable per discount (we re-use the line item id)
 *   - `code`       — the user-visible promotion code (null for auto-promotions)
 *   - `title`      — human readable name
 *   - `amount`     — negative-effect monetary amount in minor units
 *   - `currency`   — ISO 4217 currency code
 *   - `automatic`  — true when no code is required
 *   - `applies_to` — line item ids that are affected (best-effort)
 *
 * Rejected codes are surfaced via the cart `messages[]` per spec —
 * {@see extractRejectedCodes()} returns the matching error messages.
 *
 * @internal
 */
#[Package('framework')]
class DiscountMapper
{
    /**
     * @return array{applied: list<array<string, mixed>>}|array{}
     */
    public function extract(Cart $cart, SalesChannelContext $context): array
    {
        $discounts = [];

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== 'promotion') {
                continue;
            }

            $price = $lineItem->getPrice();
            $payload = $lineItem->getPayload();
            $code = $payload['code'] ?? null;

            $discounts[] = array_filter([
                'title' => $lineItem->getLabel(),
                'code' => \is_string($code) && $code !== '' ? $code : null,
                'amount' => $price !== null ? (int) round(abs($price->getTotalPrice()) * 100) : 0,
                'automatic' => !\is_string($code) || $code === '',
                'method' => 'across',
                'priority' => 1,
                'allocations' => $this->resolveAllocations($payload, $price !== null ? (int) round(abs($price->getTotalPrice()) * 100) : 0),
            ], static fn (mixed $v): bool => $v !== null && $v !== []);
        }

        return $discounts === [] ? [] : ['applied' => $discounts];
    }

    /**
     * Cart errors emitted by Shopware's promotion processor when a code is
     * rejected (unknown, expired, not applicable, …). Returned as UCP
     * `messages[]` entries with spec-mapped error codes per discount.md
     * §"Error Codes".
     *
     * Shopware promotion key → UCP code mapping:
     *
     *   promotion-not-found / promotion-code-not-found  → discount_code_invalid
     *   promotion-not-eligible                          → discount_code_not_applicable
     *   promotion-already-placed-in-cart                → discount_code_already_applied
     *   promotion-expired / promotion-out-of-date       → discount_code_expired
     *
     * @return list<array<string, mixed>>
     */
    public function extractRejectedCodes(Cart $cart): array
    {
        $messages = [];
        foreach ($cart->getErrors() as $error) {
            $key = $error->getMessageKey();
            // Shopware promotion error keys live in
            // `\Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError`
            // and siblings. They all start with `promotion-`.
            if (!str_contains($key, 'promotion')) {
                continue;
            }

            $messages[] = [
                'type' => 'warning',
                'code' => $this->mapPromotionErrorToSpecCode($key),
                'content' => $error->getMessage(),
                // `path` lets clients machine-route the error to the input
                // field that caused it — per overview.md §"Error Format".
                'path' => '$.discounts.codes',
            ];
        }

        return $messages;
    }

    private function mapPromotionErrorToSpecCode(string $shopwareKey): string
    {
        return match (true) {
            str_contains($shopwareKey, 'not-found') => 'discount_code_invalid',
            str_contains($shopwareKey, 'expired'), str_contains($shopwareKey, 'out-of-date') => 'discount_code_expired',
            str_contains($shopwareKey, 'already') => 'discount_code_already_applied',
            str_contains($shopwareKey, 'not-eligible'), str_contains($shopwareKey, 'rule') => 'discount_code_not_applicable',
            default => 'discount_code_rejected',
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{path: string, amount: int}>
     */
    private function resolveAllocations(array $payload, int $amount): array
    {
        $candidates = $payload['composition'] ?? [];
        if (!\is_array($candidates)) {
            return [];
        }

        $allocations = [];
        foreach ($candidates as $entry) {
            if (\is_array($entry) && isset($entry['id']) && \is_string($entry['id'])) {
                $allocations[] = [
                    'path' => '$.line_items[?(@.id=="' . $entry['id'] . '")]',
                    'amount' => $amount > 0 ? -$amount : 0,
                ];
            }
        }

        return $allocations;
    }
}
