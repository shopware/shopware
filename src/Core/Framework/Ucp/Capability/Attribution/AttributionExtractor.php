<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Attribution;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Validates and normalises the optional `attribution` object UCP carries on
 * cart and checkout requests per overview.md §"Attribution". The spec
 * follows GA-style fields:
 *
 *   - `campaign_id`        — opaque platform campaign id
 *   - `campaign_source`    — e.g. "chatgpt", "perplexity"
 *   - `campaign_medium`    — e.g. "agent", "embedded", "web", "voice"
 *   - `campaign_name`      — friendly name
 *   - `gclid` / `fbclid`   — click id passthroughs
 *
 * For backwards compatibility we also accept the shorter aliases
 * (`source`, `medium`, `campaign`) and normalise to the spec field names.
 * `attribution` is OPTIONAL — missing/empty input → null (no echo).
 *
 * @internal
 */
#[Package('framework')]
class AttributionExtractor
{
    private const ALLOWED_MEDIUMS = ['agent', 'embedded', 'web', 'voice', 'app', 'email', 'sms'];

    /**
     * Spec field → list of accepted aliases (first match wins).
     */
    private const ALIASES = [
        'campaign_source' => ['campaign_source', 'source', 'utm_source'],
        'campaign_medium' => ['campaign_medium', 'medium', 'utm_medium'],
        'campaign_name' => ['campaign_name', 'campaign', 'utm_campaign'],
        'campaign_id' => ['campaign_id', 'utm_id'],
    ];

    /**
     * @param array<string, mixed> $payload the full request body
     *
     * @return array<string, mixed>|null
     */
    public function extract(array $payload): ?array
    {
        $attribution = $payload['attribution'] ?? null;
        if (!\is_array($attribution) || $attribution === []) {
            return null;
        }

        $out = [];

        foreach (self::ALIASES as $specField => $aliasList) {
            foreach ($aliasList as $alias) {
                $value = $attribution[$alias] ?? null;
                if (\is_string($value) && $value !== '') {
                    $out[$specField] = $value;
                    break;
                }
            }
        }

        // Validate medium against the closed enum if we picked one up.
        if (isset($out['campaign_medium']) && !\in_array($out['campaign_medium'], self::ALLOWED_MEDIUMS, true)) {
            unset($out['campaign_medium']);
        }

        // Click-id passthroughs.
        foreach (['gclid', 'fbclid', 'msclkid'] as $clickId) {
            if (\is_string($attribution[$clickId] ?? null) && $attribution[$clickId] !== '') {
                $out[$clickId] = $attribution[$clickId];
            }
        }

        // Referrer URL — only if valid URL.
        if (\is_string($attribution['referrer_url'] ?? null)
            && filter_var($attribution['referrer_url'], \FILTER_VALIDATE_URL) !== false) {
            $out['referrer_url'] = $attribution['referrer_url'];
        }

        // Opaque agent identifiers (NOT PII).
        foreach (['agent_session_id', 'agent_user_id'] as $optionalId) {
            if (\is_string($attribution[$optionalId] ?? null) && $attribution[$optionalId] !== '') {
                $out[$optionalId] = $attribution[$optionalId];
            }
        }

        return $out === [] ? null : $out;
    }
}
