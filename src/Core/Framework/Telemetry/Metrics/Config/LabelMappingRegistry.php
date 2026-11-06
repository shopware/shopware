<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics\Config;

use Shopware\Core\Framework\Log\Package;

/**
 * Named mapping tables (`key → label value`) declared under `shopware.telemetry.metrics.mappings`, used by
 * the label resolvers to bucket open identifier sets (entity names, route names, event names, ...) into
 * bounded label values.
 *
 * A key is exact, or a prefix when it ends with `*`. Exact beats prefix; the longest prefix wins. Resolution
 * order per key: configured entry → the resolver's code-level fallback → the replacement value. Configured
 * entries win so an extension or operator can always correct a code-level heuristic.
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
    private array $compiled = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    private array $cache = [];

    /**
     * @param array<string, array<string, string>> $mappings mapping name → (key → label value)
     */
    public function __construct(
        private readonly array $mappings,
        private readonly string $replacementValue,
    ) {
    }

    /**
     * @param \Closure(string): ?string|null $codeLevelFallback resolver-owned rule, consulted when no configured entry matches
     */
    public function resolve(string $mapping, string $key, ?\Closure $codeLevelFallback = null): string
    {
        return $this->lookupCached($mapping, $key)
            ?? ($codeLevelFallback !== null ? $codeLevelFallback($key) : null)
            ?? $this->replacementValue;
    }

    private function lookupCached(string $mapping, string $key): ?string
    {
        if (\array_key_exists($key, $this->cache[$mapping] ?? [])) {
            return $this->cache[$mapping][$key];
        }

        return $this->cache[$mapping][$key] = $this->lookup($mapping, $key);
    }

    private function lookup(string $mapping, string $key): ?string
    {
        $table = $this->compiled[$mapping] ??= $this->compile($this->mappings[$mapping] ?? []);

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
