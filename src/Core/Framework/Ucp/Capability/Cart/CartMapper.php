<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Attribution\AttributionExtractor;
use Shopware\Core\Framework\Ucp\Capability\Discount\DiscountMapper;
use Shopware\Core\Framework\Ucp\Capability\Loyalty\LoyaltyAggregator;
use Shopware\Core\Framework\Ucp\Capability\Signals\SignalsExtractor;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Translates a Shopware {@see Cart} into the UCP cart response shape defined
 * in `ucp/source/schemas/shopping/cart.json#/$defs/cart_resp`.
 *
 * Optional extension fields (only emitted when negotiated):
 *   - `discounts[]`          — when `dev.ucp.shopping.discount` is active
 *   - `loyalty[]`            — when `dev.ucp.shopping.loyalty` is active
 *   - `signals[]`            — echo back of trusted platform signals
 *   - `attribution`          — echo back of normalised attribution
 *
 * @internal
 */
#[Package('framework')]
class CartMapper
{
    public function __construct(
        private readonly DiscountMapper $discountMapper,
        private readonly LoyaltyAggregator $loyaltyAggregator,
        private readonly SignalsExtractor $signalsExtractor,
        private readonly AttributionExtractor $attributionExtractor,
    ) {
    }

    /**
     * @param array<string, mixed> $platformRequest Original request body — used for signals + attribution echo.
     * @param bool $signatureVerified Pass-through from the request resolver. When false,
     *                                signals are dropped per overview.md §"Signals".
     *
     * @return array<string, mixed>
     */
    public function toResponse(
        Cart $cart,
        SalesChannelContext $salesChannelContext,
        ?string $continueUrl = null,
        ?UcpRequestContext $ucpContext = null,
        array $platformRequest = [],
        bool $signatureVerified = false
    ): array {
        $currency = $salesChannelContext->getCurrency()->getIsoCode();

        $lineItems = [];
        foreach ($cart->getLineItems() as $lineItem) {
            // Promotion items are surfaced via `discounts[]` — don't double-count.
            if ($lineItem->getType() === 'promotion') {
                continue;
            }
            $lineItems[] = $this->mapLineItem($lineItem, $currency);
        }

        $payload = [
            'id' => $cart->getToken(),
            'line_items' => $lineItems,
            'currency' => $currency,
            'totals' => $this->mapTotals($cart, $currency),
            'context' => $this->mapContext($salesChannelContext),
            'messages' => $this->mapMessages($cart),
            'expires_at' => $this->computeExpiresAt(),
        ];

        if ($continueUrl !== null) {
            $payload['continue_url'] = $continueUrl;
        }

        if ($ucpContext !== null) {
            // dev.ucp.shopping.discount
            if ($ucpContext->intersection->has('dev.ucp.shopping.discount')) {
                $discounts = $this->discountMapper->extract($cart, $salesChannelContext);
                if ($discounts !== []) {
                    $payload['discounts'] = $discounts;
                }
                // Surface promotion-rejection messages alongside the discount block.
                $rejected = $this->discountMapper->extractRejectedCodes($cart);
                if ($rejected !== []) {
                    $payload['messages'] = array_merge($payload['messages'], $rejected);
                }
            }

            // dev.ucp.shopping.loyalty
            if ($ucpContext->intersection->has('dev.ucp.shopping.loyalty')) {
                $loyalty = $this->loyaltyAggregator->aggregate($salesChannelContext);
                if ($loyalty !== []) {
                    $payload['loyalty'] = $loyalty;
                }
            }

            // signals echo — only when the request was cryptographically
            // authenticated (signature_policy=strict + valid signature).
            $signals = $this->signalsExtractor->extract($platformRequest, $ucpContext, $signatureVerified);
            if ($signals !== []) {
                $payload['signals'] = $signals;
            }

            // attribution echo
            $attribution = $this->attributionExtractor->extract($platformRequest);
            if ($attribution !== null) {
                $payload['attribution'] = $attribution;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLineItem(LineItem $lineItem, string $currency): array
    {
        $price = $lineItem->getPrice();
        $payload = $lineItem->getPayload();
        $itemId = (\is_string($payload['productNumber'] ?? null) && $payload['productNumber'] !== '')
            ? $payload['productNumber']
            : ($lineItem->getReferencedId() ?? $lineItem->getId());

        $out = [
            'id' => $lineItem->getId(),
            'item' => [
                'id' => $itemId,
                'title' => $lineItem->getLabel(),
            ],
            'quantity' => $lineItem->getQuantity(),
        ];

        if ($price !== null) {
            $unitAmount = self::toMinorUnits($price->getUnitPrice());
            $lineAmount = self::toMinorUnits($price->getTotalPrice());
            $out['item']['price'] = $unitAmount;
            $out['price'] = [
                'amount' => $unitAmount,
                'currency' => $currency,
            ];
            $out['line_total'] = [
                'amount' => $lineAmount,
                'currency' => $currency,
            ];
            $out['totals'] = [
                ['type' => 'subtotal', 'amount' => $lineAmount],
                ['type' => 'total', 'amount' => $lineAmount],
            ];
        }

        $description = $lineItem->getDescription();
        if (\is_string($description) && $description !== '') {
            $out['item']['description'] = $description;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapTotals(Cart $cart, string $currency): array
    {
        $price = $cart->getPrice();

        $totals = [
            [
                'type' => 'subtotal',
                'amount' => self::toMinorUnits($price->getPositionPrice()),
                'currency' => $currency,
            ],
            [
                'type' => 'tax',
                'amount' => self::toMinorUnits($price->getCalculatedTaxes()->getAmount()),
                'currency' => $currency,
            ],
        ];

        // Net discount line (sum of promotion line items, always negative).
        $discountTotal = 0.0;
        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() === 'promotion') {
                $linePrice = $lineItem->getPrice();
                if ($linePrice !== null) {
                    $discountTotal += $linePrice->getTotalPrice();
                }
            }
        }
        if ($discountTotal !== 0.0) {
            $totals[] = [
                'type' => 'discount',
                'amount' => self::toMinorUnits($discountTotal),
                'currency' => $currency,
            ];
        }

        $totals[] = [
            'type' => 'total',
            'amount' => self::toMinorUnits($price->getTotalPrice()),
            'currency' => $currency,
        ];

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapContext(SalesChannelContext $context): array
    {
        $address = $context->getShippingLocation()->getAddress() ?? null;
        $country = $context->getShippingLocation()->getCountry();

        $ctx = [
            'address_country' => $country->getIso(),
            'currency' => $context->getCurrency()->getIsoCode(),
            'language' => $context->getLanguageId(),
        ];

        if ($address !== null) {
            $state = $address->getCountryState();
            if ($state !== null) {
                $ctx['address_region'] = $state->getShortCode();
            }
            if ($address->getZipcode() !== null) {
                $ctx['postal_code'] = $address->getZipcode();
            }
        }

        return $ctx;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapMessages(Cart $cart): array
    {
        $messages = [];
        foreach ($cart->getErrors() as $error) {
            // Promotion errors are surfaced separately via DiscountMapper, skip here.
            if (str_contains($error->getMessageKey(), 'promotion')) {
                continue;
            }

            $messages[] = [
                'type' => $error->isPersistent() ? 'error' : 'info',
                'code' => $error->getMessageKey(),
                'content' => $error->getMessage(),
                'severity' => $this->mapSeverity($error->getLevel()),
            ];
        }

        return $messages;
    }

    /**
     * Per UCP cart.md, `expires_at` is RECOMMENDED. Shopware carts don't have
     * a fixed TTL — they live as long as the session — so we publish a
     * conservative 24h horizon so platforms can plan re-validation cadence.
     */
    private function computeExpiresAt(): string
    {
        return gmdate('c', time() + 86400);
    }

    private function mapSeverity(int $level): string
    {
        return match (true) {
            $level >= 20 => 'unrecoverable',
            $level >= 10 => 'requires_buyer_input',
            default => 'recoverable',
        };
    }

    private static function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
