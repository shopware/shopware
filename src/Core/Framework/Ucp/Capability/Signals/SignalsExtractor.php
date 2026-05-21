<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Signals;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Validates and normalises `signals[]` per UCP overview.md §"Signals".
 *
 * Signals are platform-provided hints (buyer IP, geography, intent vector,
 * fraud-risk score, …) that the business MAY use for tax/shipping pre-quote
 * and fraud heuristics. Because signals come from the platform — not from
 * the buyer's browser — the business MUST only trust them when:
 *
 *   1. The platform's request is RFC 9421 signed (verified upstream).
 *   2. The signal namespace is in the business's trust list (or `dev.ucp.*`
 *      well-known prefixes).
 *
 * Untrusted signals are dropped silently (logged) — they don't fail the
 * request. The spec is intentionally non-fatal here.
 *
 * @internal
 */
#[Package('framework')]
class SignalsExtractor
{
    /**
     * Signal namespaces accepted unconditionally (overview.md §Signals examples).
     */
    private const TRUSTED_NAMESPACE_PREFIXES = [
        'dev.ucp.',
    ];

    /**
     * Maximum number of signals per request — avoid DoS via unbounded arrays.
     */
    private const MAX_SIGNALS = 32;

    /**
     * Extract trusted signals from the request payload. Returns the spec
     * object-map shape:
     *
     *   { "dev.ucp.buyer_ip": "203.0.113.42", "dev.ucp.buyer_country": "DE" }
     *
     * Per UCP overview.md §"Signals" the business MUST NOT trust signals
     * unless the inbound platform request was cryptographically authenticated
     * (RFC 9421). When the signature was not verified, signals are dropped.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function extract(array $payload, UcpRequestContext $ucpContext, bool $signatureVerified = false): array
    {
        $raw = $payload['signals'] ?? null;
        if (!\is_array($raw) || $raw === []) {
            return [];
        }

        if (!$signatureVerified) {
            return [];
        }

        $allowlist = $ucpContext->config->getPlatformAllowlist() ?? [];
        $trustedExtraNamespaces = $this->trustedNamespaceListFor($allowlist);

        $out = [];
        $count = 0;
        foreach ($raw as $name => $value) {
            if ($count >= self::MAX_SIGNALS) {
                break;
            }
            if (!\is_string($name) || $name === '') {
                continue;
            }
            $this->assertNameSafe($name);

            if (!$this->isTrusted($name, $trustedExtraNamespaces)) {
                continue;
            }

            $out[$name] = $value;
            ++$count;
        }

        return $out;
    }

    /**
     * Convenience reader for runtime callers — e.g. a tax provider that
     * wants `dev.ucp.buyer_ip` for geolocation.
     *
     * @param array<string, mixed> $signals signal map (output of {@see extract()})
     */
    public static function get(array $signals, string $name): mixed
    {
        return $signals[$name] ?? null;
    }

    /**
     * Validate a signal name strictly. Throws `signals_untrusted` if the
     * input is malformed beyond drop-silently policy (e.g. trying to inject
     * a control character or attempting a known-bad pattern).
     */
    public function assertNameSafe(string $name): void
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw UcpException::signalsUntrusted('signal name contains control characters');
        }
    }

    /**
     * @param array<int|string, mixed> $allowlist
     *
     * @return list<string>
     */
    private function trustedNamespaceListFor(array $allowlist): array
    {
        $namespaces = [];
        foreach ($allowlist as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $ns = $entry['trust_signal_namespace'] ?? null;
            if (\is_string($ns) && $ns !== '') {
                $namespaces[] = $ns;
            }
        }

        return $namespaces;
    }

    /**
     * @param list<string> $additionalNamespaces
     */
    private function isTrusted(string $signalName, array $additionalNamespaces): bool
    {
        foreach (self::TRUSTED_NAMESPACE_PREFIXES as $prefix) {
            if (str_starts_with($signalName, $prefix)) {
                return true;
            }
        }
        foreach ($additionalNamespaces as $prefix) {
            if (str_starts_with($signalName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
