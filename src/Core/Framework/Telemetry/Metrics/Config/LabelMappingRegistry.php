<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
use Shopware\Core\Framework\Log\Package;

/**
 * Named mapping tables (`key → label value`) declared under `shopware.telemetry.metrics.mappings`, used by
 * the label resolvers to bucket open identifier sets (entity names, route names, event names, ...) into
 * bounded label values.
 *
 * A key is exact, or a prefix when it ends with `*`. Exact beats prefix; the longest prefix wins. Resolution
 * order per key: configured entry → the resolver's code-level fallback → app-contributed entry → the
 * replacement value. Configured entries win so an extension or operator can always correct a code-level
 * heuristic; app entries are consulted only for keys neither configuration nor the code-level rules claim,
 * so an app can label its own identifiers but never relabel a series core already produces.
 *
 * App-contributed entries (persisted at install, loaded fail-soft) are compiled as their own tier on first
 * use; apps add, never override — declaring a key (or a covering prefix) in project config also neutralizes
 * an unwanted app entry, the operator's kill switch. The `accept_app_contributions` policy gates the whole
 * channel (see {@see AppContributionPolicy}): fleet operators whose tenants upload arbitrary apps restrict
 * it to `no-new-values` or `none`.
 *
 * Table lookups are memoized per mapping and key, the callback-based fallback is not, as it may produce
 * different results for the same key. The identifier keyspaces are finite and fixed per deployment,
 * so no eviction is implemented.
 *
 * @internal
 */
#[Package('framework')]
class LabelMappingRegistry
{
    /**
     * @var array<string, array{exact: array<string, string>, prefixes: list<array{string, string}>}>
     */
    private array $compiledDeclared = [];

    /**
     * @var array<string, array{exact: array<string, string>, prefixes: list<array{string, string}>}>
     */
    private array $compiledApp = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private array $cacheDeclared = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private array $cacheApp = [];

    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $appEntries = null;

    private readonly AppContributionPolicy $appContributionPolicy;

    /**
     * @param array<string, array<string, string>> $mappings mapping name → (key → label value)
     * @param (\Closure(): AppTelemetryConfigLoader)|null $appConfigLoader injected as a service closure:
     *                                                                     an eager reference closes a container cycle — loader → AppFeatureStorage →
     *                                                                     AppFeatureDefinitionRegistry → feature definitions → entity repositories →
     *                                                                     DAL instrumentation → resolvers → this registry
     */
    public function __construct(
        private readonly array $mappings,
        private readonly string $replacementValue,
        private readonly ?\Closure $appConfigLoader = null,
        private readonly ?LoggerInterface $logger = null,
        string $acceptAppContributions = 'all',
    ) {
        $this->appContributionPolicy = AppContributionPolicy::from($acceptAppContributions);
    }

    /**
     * @param \Closure(string): ?string|null $codeLevelFallback resolver-owned rule, consulted when no configured entry matches
     */
    public function resolve(string $mapping, string $key, ?\Closure $codeLevelFallback = null): string
    {
        return $this->lookupDeclared($mapping, $key)
            ?? ($codeLevelFallback !== null ? $codeLevelFallback($key) : null)
            ?? $this->lookupApp($mapping, $key)
            ?? $this->replacementValue;
    }

    private function lookupDeclared(string $mapping, string $key): ?string
    {
        if (\array_key_exists($key, $this->cacheDeclared[$mapping] ?? [])) {
            return $this->cacheDeclared[$mapping][$key];
        }

        $table = $this->compiledDeclared[$mapping] ??= $this->compile($this->mappings[$mapping] ?? []);

        return $this->cacheDeclared[$mapping][$key] = $this->lookupIn($table, $key);
    }

    private function lookupApp(string $mapping, string $key): ?string
    {
        if (\array_key_exists($key, $this->cacheApp[$mapping] ?? [])) {
            return $this->cacheApp[$mapping][$key];
        }

        $table = $this->compiledApp[$mapping] ??= $this->compile($this->appEntriesFor($mapping));

        return $this->cacheApp[$mapping][$key] = $this->lookupIn($table, $key);
    }

    /**
     * @param array{exact: array<string, string>, prefixes: list<array{string, string}>} $table
     */
    private function lookupIn(array $table, string $key): ?string
    {
        if (isset($table['exact'][$key])) {
            return $table['exact'][$key];
        }

        foreach ($table['prefixes'] as [$prefix, $value]) {
            if (str_starts_with($key, $prefix)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function appEntriesFor(string $mapping): array
    {
        $declared = $this->mappings[$mapping] ?? [];
        $fromApps = $this->appEntries()[$mapping] ?? [];

        if ($this->appContributionPolicy === AppContributionPolicy::NO_NEW_VALUES && $fromApps !== []) {
            $fromApps = $this->dropNewValueEntries($mapping, $fromApps, $declared);
        }

        foreach (array_intersect_key($fromApps, $declared) as $key => $_) {
            $this->logger?->debug(\sprintf(
                'Dropped app telemetry entry for mapping "%s" key "%s": the key is declared in config.',
                $mapping,
                $key,
            ));
        }

        // keys declared in config never reach the app tier; prefix overlaps are shadowed by tier order at lookup
        return array_diff_key($fromApps, $declared);
    }

    /**
     * The `no-new-values` predicate: an app entry is accepted iff its value already occurs in the
     * operator-declared table — grouping into the operator's taxonomy stays possible, minting new label
     * values does not. The reference set is the configured table only: never other apps' contributions,
     * so the outcome is independent of app installation order, and not values that only the resolver's
     * code-level rule produces.
     *
     * @param array<string, string> $fromApps
     * @param array<string, string> $declared
     *
     * @return array<string, string>
     */
    private function dropNewValueEntries(string $mapping, array $fromApps, array $declared): array
    {
        $declaredValues = array_values(array_unique($declared));

        $accepted = [];
        foreach ($fromApps as $key => $value) {
            if (\in_array($value, $declaredValues, true)) {
                $accepted[$key] = $value;

                continue;
            }

            $this->logger?->debug(\sprintf(
                'Dropped app telemetry entry for mapping "%s" key "%s": value "%s" is not declared in config (accept_app_contributions: no-new-values).',
                $mapping,
                $key,
                $value,
            ));
        }

        return $accepted;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function appEntries(): array
    {
        if ($this->appContributionPolicy === AppContributionPolicy::NONE || $this->appConfigLoader === null) {
            return [];
        }

        return $this->appEntries ??= ($this->appConfigLoader)()->load()['mappings'];
    }

    /**
     * @param array<string, string> $entries
     *
     * @return array{exact: array<string, string>, prefixes: list<array{string, string}>}
     */
    private function compile(array $entries): array
    {
        $exact = [];
        $prefixes = [];

        foreach ($entries as $key => $value) {
            if (str_ends_with($key, '*')) {
                $prefixes[] = [substr($key, 0, -1), $value];
            } else {
                $exact[$key] = $value;
            }
        }

        // longest prefix wins
        usort($prefixes, static fn (array $a, array $b): int => \strlen($b[0]) <=> \strlen($a[0]));

        return ['exact' => $exact, 'prefixes' => $prefixes];
    }
}
