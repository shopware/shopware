<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Attribution\AttributionExtractor;
use Shopware\Core\Framework\Ucp\Capability\BuyerConsent\BuyerConsentExtension;
use Shopware\Core\Framework\Ucp\Capability\BuyerConsent\BuyerConsentMapper;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentExtension;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentMapper;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Payment\AvailableInstrumentResolver;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpResponseEnvelopeListener;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Maps a Shopware cart + checkout-in-progress into the UCP checkout response
 * shape. Heavy lifting (line items, totals, discounts, loyalty) delegates to
 * {@see CartMapper}; this class layers checkout-specific fields on top:
 *
 *   - `status`                — lifecycle state (incomplete / completed / …)
 *   - `messages[]`            — severity-resolved errors
 *   - `continue_url`
 *   - `buyer`                 — incl. nested `buyer.consent` when buyer-consent
 *                               capability is in the intersection
 *   - `fulfillment`           — when fulfillment extension is in the intersection
 *   - `available_instruments` — intersection of platform-accepted + business-
 *                               -offered payment instruments per overview.md
 *   - `ucp.payment_handlers`  — handlers in the ucp envelope (checkout only)
 *   - `attribution`           — echo of normalised attribution
 *
 * @internal
 */
#[Package('framework')]
class CheckoutMapper
{
    public function __construct(
        private readonly CartMapper $cartMapper,
        private readonly CheckoutStatusResolver $statusResolver,
        private readonly ContinueUrlBuilder $continueUrlBuilder,
        private readonly FulfillmentMapper $fulfillmentMapper,
        private readonly BuyerConsentMapper $buyerConsentMapper,
        private readonly AttributionExtractor $attributionExtractor,
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly AvailableInstrumentResolver $instrumentResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $platformRequest
     *
     * @return array<string, mixed>
     */
    public function toResponse(
        Cart $cart,
        SalesChannelContext $sc,
        UcpSalesChannelConfigEntity $config,
        bool $orderJustPlaced = false,
        ?UcpRequestContext $ucpContext = null,
        array $platformRequest = [],
        bool $signatureVerified = false
    ): array {
        $base = $this->cartMapper->toResponse($cart, $sc, null, $ucpContext, $platformRequest, $signatureVerified);
        $continueUrl = $this->continueUrlBuilder->buildForCheckout($config, $sc, $cart->getToken());

        $base['id'] = $cart->getToken();
        $base['status'] = $this->statusResolver->resolve($cart, $sc, $orderJustPlaced);
        $base['messages'] = $this->statusResolver->buildMessages($cart);
        $base['continue_url'] = $continueUrl;
        $base['links'] = $base['links'] ?? [];

        $customer = $sc->getCustomer();
        if ($customer !== null) {
            $base['buyer'] = [
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'last_name' => $customer->getLastName(),
            ];
        }

        if ($ucpContext !== null) {
            // Fulfillment extension
            if ($ucpContext->intersection->has(FulfillmentExtension::NAME)) {
                $base['fulfillment'] = $this->fulfillmentMapper->fromCart($cart, $sc);
            }

            // Buyer consent extension
            if ($ucpContext->intersection->has(BuyerConsentExtension::NAME)) {
                $incomingConsent = $platformRequest['buyer']['consent'] ?? null;
                $consent = $this->buyerConsentMapper->applyAndReturn(
                    \is_array($incomingConsent) ? $incomingConsent : null,
                    $sc,
                    $cart->getToken()
                );
                if ($consent !== null && $consent !== []) {
                    $base['buyer'] = ($base['buyer'] ?? []) + ['consent' => $consent];
                }
            }

            // attribution echo
            $attribution = $this->attributionExtractor->extract($platformRequest);
            if ($attribution !== null) {
                $base['attribution'] = $attribution;
            }

            // available_instruments: platform∩business intersection
            $base['available_instruments'] = $this->instrumentResolver->resolve(
                $platformRequest['payment']['accepted_instruments'] ?? null,
                $sc,
                $ucpContext
            );
            $base['payment'] = [
                'instruments' => $base['available_instruments'],
            ];
        }

        return $base;
    }

    /**
     * Build the `ucp` envelope segment for a checkout response. The base
     * envelope (version + capabilities) is added by
     * {@see UcpResponseEnvelopeListener};
     * this method adds the checkout-specific `payment_handlers` array per
     * UCP checkout-rest.md.
     *
     * @return array<string, mixed>
     */
    public function buildUcpEnvelopeExtras(SalesChannelContext $sc, Context $context): array
    {
        return [
            'payment_handlers' => $this->paymentHandlerRegistry->describeForSalesChannel(
                $sc->getSalesChannelId(),
                $context
            ),
        ];
    }
}
