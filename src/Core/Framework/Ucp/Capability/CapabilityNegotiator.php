<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Implements the capability intersection algorithm from
 * `ucp/docs/specification/overview.md#intersection-algorithm`:
 *
 *   1. Compute intersection by capability name.
 *   2. For each capability, select the HIGHEST mutually supported version.
 *      If no version is mutually supported, exclude the capability.
 *   3. Prune extensions whose parent(s) are no longer present.
 *      For multi-parent extensions, at least one parent must remain.
 *   4. Repeat pruning until fixed point (handles transitive chains).
 *
 * Inputs are the `capabilities` map from each profile, shaped:
 *
 *   [
 *     "dev.ucp.shopping.cart" => [
 *       ["version" => "2026-01-23", "spec" => "...", "schema" => "..."],
 *       ["version" => "2026-01-11", "spec" => "...", "schema" => "..."]
 *     ],
 *     ...
 *   ]
 *
 * @internal
 */
#[Package('framework')]
class CapabilityNegotiator
{
    /**
     * @param array<string, list<array<string, mixed>>> $businessCapabilities
     * @param array<string, list<array<string, mixed>>> $platformCapabilities
     */
    public function negotiate(
        array $businessCapabilities,
        array $platformCapabilities,
        string $protocolVersion = UcpVersion::CURRENT
    ): CapabilityIntersection {
        // 1. Intersect by name
        $intersection = [];
        foreach ($businessCapabilities as $name => $businessEntries) {
            if (!isset($platformCapabilities[$name])) {
                continue;
            }
            $intersection[$name] = $this->intersectVersions($businessEntries, $platformCapabilities[$name]);
        }

        // 2. Drop capabilities with no mutual version
        $intersection = array_filter($intersection, static fn (?array $entry): bool => $entry !== null);

        // 3/4. Prune orphaned extensions iteratively
        $intersection = $this->pruneOrphanedExtensions($intersection, $businessCapabilities);

        return new CapabilityIntersection(
            capabilities: array_map(static fn (array $entry): array => [$entry], $intersection),
            protocolVersion: $protocolVersion,
        );
    }

    /**
     * @param list<array<string, mixed>> $businessEntries
     * @param list<array<string, mixed>> $platformEntries
     *
     * @return array<string, mixed>|null returns the highest mutual version entry or null
     */
    private function intersectVersions(array $businessEntries, array $platformEntries): ?array
    {
        $platformVersions = [];
        foreach ($platformEntries as $entry) {
            if (isset($entry['version']) && \is_string($entry['version'])) {
                $platformVersions[$entry['version']] = $entry;
            }
        }

        $best = null;
        $bestVersion = null;

        foreach ($businessEntries as $entry) {
            $version = $entry['version'] ?? null;
            if (!\is_string($version) || !UcpVersion::isValidFormat($version)) {
                continue;
            }
            if (!isset($platformVersions[$version])) {
                continue;
            }
            if ($bestVersion === null || UcpVersion::compare($version, $bestVersion) > 0) {
                $best = $entry;
                $bestVersion = $version;
            }
        }

        return $best;
    }

    /**
     * @param array<string, array<string, mixed>> $intersection (name => single entry)
     * @param array<string, list<array<string, mixed>>> $businessCapabilities (full original map, used to inspect `extends`)
     *
     * @return array<string, array<string, mixed>>
     */
    private function pruneOrphanedExtensions(array $intersection, array $businessCapabilities): array
    {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($intersection as $name => $entry) {
                $extends = $entry['extends'] ?? null;
                if ($extends === null) {
                    // Try the business's own declaration for `extends` if intersection entry didn't carry it
                    $extends = $this->resolveExtendsFromBusiness($name, $businessCapabilities);
                    if ($extends === null) {
                        continue;
                    }
                }

                $parents = \is_array($extends) ? $extends : [$extends];
                $satisfied = false;
                foreach ($parents as $parent) {
                    if (isset($intersection[$parent])) {
                        $satisfied = true;
                        break;
                    }
                }

                if (!$satisfied) {
                    unset($intersection[$name]);
                    $changed = true;
                }
            }
        }

        return $intersection;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $businessCapabilities
     *
     * @return string|list<string>|null
     */
    private function resolveExtendsFromBusiness(string $name, array $businessCapabilities): string|array|null
    {
        $entries = $businessCapabilities[$name] ?? [];
        foreach ($entries as $entry) {
            if (isset($entry['extends'])) {
                $extends = $entry['extends'];
                if (\is_string($extends)) {
                    return $extends;
                }
                if (\is_array($extends)) {
                    return array_values(array_filter($extends, 'is_string'));
                }
            }
        }

        return null;
    }
}
