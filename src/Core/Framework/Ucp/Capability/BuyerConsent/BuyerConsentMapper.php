<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\BuyerConsent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Read/write helper for `buyer.consent` per
 * `ucp/docs/specification/buyer-consent.md`. The platform sends:
 *
 * ```
 * buyer: {
 *   consent: {
 *     terms_of_service:    { granted: true,  granted_at: "2026-05-20T12:00:00Z" },
 *     marketing_email:     { granted: false },
 *     data_sharing_agent:  { granted: true,  scope: ["catalog", "cart"] },
 *     gdpr_data_processing:{ granted: true,  jurisdiction: "EU", basis: "consent" }
 *   }
 * }
 * ```
 *
 * The business persists the consent decisions, scopes downstream processing
 * to them, and **MUST** echo what it stored in subsequent responses so the
 * platform can confirm coverage.
 *
 * @internal
 */
#[Package('framework')]
class BuyerConsentMapper
{
    /**
     * Consent fields the business honours; unknown keys are ignored per spec.
     */
    public const SUPPORTED_FIELDS = [
        'terms_of_service',
        'gdpr_data_processing',
        'data_sharing_agent',
        'marketing_email',
        'marketing_sms',
        'data_retention_extended',
        'profiling',
    ];

    public function __construct(
        private readonly ConsentStore $store,
    ) {
    }

    /**
     * Reads the consent block of a request body, persists it for the cart's
     * context token, and returns the canonical (whitelisted) snapshot to
     * include in the response.
     *
     * @param array<string, mixed>|null $consent
     *
     * @return array<string, mixed>|null
     */
    public function applyAndReturn(?array $consent, SalesChannelContext $context, string $checkoutId): ?array
    {
        $snapshot = $this->store->load($checkoutId);

        if (\is_array($consent) && $consent !== []) {
            $snapshot = $this->mergeConsent($snapshot ?? [], $consent);
            $this->store->save($checkoutId, $context->getSalesChannelId(), $snapshot);
        }

        return $snapshot;
    }

    /**
     * @param array<string, mixed>|null $consent
     */
    public static function asDataBag(?array $consent): RequestDataBag
    {
        return new RequestDataBag(['consent' => $consent ?? []]);
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $incoming
     *
     * @return array<string, mixed>
     */
    private function mergeConsent(array $existing, array $incoming): array
    {
        foreach ($incoming as $field => $decision) {
            if (!\in_array($field, self::SUPPORTED_FIELDS, true)) {
                continue;
            }
            if (!\is_array($decision)) {
                continue;
            }

            $existing[$field] = $this->normaliseDecision($decision);
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $decision
     *
     * @return array<string, mixed>
     */
    private function normaliseDecision(array $decision): array
    {
        $granted = (bool) ($decision['granted'] ?? false);

        $clean = ['granted' => $granted];

        if ($granted) {
            $grantedAt = $decision['granted_at'] ?? gmdate('c');
            if (\is_string($grantedAt)) {
                $clean['granted_at'] = $grantedAt;
            }
            // Optional scope ([list of strings]) and jurisdiction passthrough.
            if (\is_array($decision['scope'] ?? null)) {
                $clean['scope'] = array_values(array_filter(
                    $decision['scope'],
                    static fn ($s): bool => \is_string($s)
                ));
            }
            foreach (['jurisdiction', 'basis', 'policy_version'] as $passthrough) {
                if (\is_string($decision[$passthrough] ?? null)) {
                    $clean[$passthrough] = $decision[$passthrough];
                }
            }
        } else {
            // Even denials must carry a timestamp for audit.
            $deniedAt = $decision['denied_at'] ?? gmdate('c');
            if (\is_string($deniedAt)) {
                $clean['denied_at'] = $deniedAt;
            }
        }

        return $clean;
    }
}
