<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Opt-in interface implemented by Shopware payment plugins (Stripe, Mollie,
 * Adyen, PayPal, Klarna, …) to advertise themselves as a UCP payment handler.
 *
 * A handler is responsible for:
 *
 *  - DESCRIBING itself in the profile (`describe`)
 *  - TRANSLATING an inbound UCP payment instrument into a Shopware payment
 *    method id + token-carrying struct that the existing PaymentProcessor
 *    can consume (`prepareInstrument`)
 *
 * The cryptographic acquisition of the token (tokenisation, 3DS dance, etc.)
 * happens **outside** of Shopware on the platform side. We only ever see an
 * opaque credential that we hand off to the PSP via the existing payment
 * pipeline.
 *
 * Plugins register their handler service with the `ucp.payment_handler` DI
 * tag, supplying `handler_id` (the reverse-domain identifier, e.g.
 * `com.stripe.tokenizer`).
 */
#[Package('framework')]
interface UcpPaymentHandlerInterface
{
    public function getNameId(): string;

    public function describe(SalesChannelContext $context): UcpPaymentHandlerDescriptor;

    /**
     * Translate a UCP payment instrument payload to the Shopware-side
     * payment method id + opaque credential to be handed to the existing
     * payment processor at "complete checkout" time.
     *
     * @param array<string, mixed> $instrumentPayload
     *
     * @return array{paymentMethodId: string, token: string, displayLast4?: string, displayBrand?: string}
     */
    public function prepareInstrument(array $instrumentPayload, SalesChannelContext $context): array;

    /**
     * Capability hint used by the tokenization controller / registry to
     * decide whether `tokenize()` is worth calling. MUST be `true` for
     * handlers that wrap a real PSP capable of accepting raw credentials,
     * `false` for handlers that don't (e.g. invoice, prepayment, COD).
     */
    public function supportsTokenisation(): bool;

    /**
     * OPTIONAL: tokenize a raw credential per `tokenization-guide.md`.
     * Handlers that don't implement tokenisation return null (the controller
     * surfaces this as HTTP 501).
     *
     * Implementations MUST never persist or log the raw credential — pass it
     * straight to the PSP and return their opaque token. Throw on PSP error;
     * the controller maps to 502.
     *
     * @param array<string, mixed> $credential
     *
     * @return array{token: string, expires_at?: string|null, instrument_summary?: array<string, mixed>}|null
     */
    public function tokenize(string $type, array $credential, SalesChannelContext $context): ?array;
}
