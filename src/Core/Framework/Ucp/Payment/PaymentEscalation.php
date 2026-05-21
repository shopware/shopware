<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Payment;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Soft-failure exception payment handlers throw when a checkout cannot be
 * completed inline and the buyer must finish an external step first
 * (3DS challenge, Klarna confirmation page, bank-redirect, …).
 *
 * UCP overview.md models this as the `requires_escalation` checkout status:
 * the response carries a `continue_url` for the buyer to visit, and the
 * platform is expected to **NOT** retry `complete_checkout` until the
 * external flow signals completion.
 *
 * Distinct from {@see \Throwable} subclasses that map to UCP error responses:
 * this is a normal HTTP 200 with `status: "requires_escalation"`, not an
 * error.
 *
 * @internal
 */
#[Package('framework')]
class PaymentEscalation extends \RuntimeException
{
    public const KIND_SCA_3DS = 'sca_3ds';
    public const KIND_BANK_REDIRECT = 'bank_redirect';
    public const KIND_WALLET_REDIRECT = 'wallet_redirect';
    public const KIND_OTP = 'otp';

    public function __construct(
        public readonly string $continueUrl,
        public readonly string $kind = self::KIND_SCA_3DS,
        public readonly ?\DateTimeImmutable $expiresAt = null,
        public readonly ?string $method = null,
        string $message = 'Payment requires buyer escalation',
    ) {
        parent::__construct($message);
    }
}
