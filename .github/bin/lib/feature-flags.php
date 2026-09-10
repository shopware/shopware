<?php declare(strict_types=1);

/**
 * Feature-flag registry helpers for the matrix generators.
 *
 * The matrix jobs only check the repository out — no `composer install`, so no Symfony YAML
 * component and no autoloader. Hence the hand-rolled reader below: it understands the single
 * shape `feature.yaml` uses, a flat list of `- name:` blocks with scalar keys, and nothing else.
 */

const SHOPWARE_FEATURE_CONFIG = __DIR__ . '/../../../src/Core/Framework/Resources/config/packages/feature.yaml';

/**
 * The test lanes that cover the in-flight majors: one `FEATURE_ALL` value per major that has not
 * shipped yet, so each major's release state is exercised without the next one bleeding in.
 *
 * Falls back to the "all majors at once" lane when no unreleased major is registered — that is the
 * state of a maintenance branch whose major has already shipped, and it keeps the lane meaningful
 * for the major flags that are not named after a version.
 *
 * @return list<string> e.g. ['v6.8.0.0', 'v6.9.0.0'], oldest major first
 */
function shopware_major_lanes(string $featureConfigPath = SHOPWARE_FEATURE_CONFIG): array
{
    $lanes = shopware_in_flight_majors($featureConfigPath);

    return $lanes === [] ? ['major'] : $lanes;
}

/**
 * @return list<string> the registered majors that are still switched off by default
 */
function shopware_in_flight_majors(string $featureConfigPath = SHOPWARE_FEATURE_CONFIG): array
{
    $majors = [];

    foreach (shopware_read_feature_flags($featureConfigPath) as $name => $flag) {
        // Only majors named after their version identify a release state; the other major flags
        // (JSON_LD_DATA, ACCESSIBILITY_TWEAKS, ...) ride along in every lane.
        if (!\preg_match('/^v\d+\.\d+\.\d+\.\d+$/i', $name)) {
            continue;
        }

        // `default: true` means the major has shipped, so it is no longer a lane of its own.
        if (($flag['major'] ?? 'false') !== 'true' || ($flag['default'] ?? 'false') === 'true') {
            continue;
        }

        $majors[] = $name;
    }

    \usort($majors, static fn (string $a, string $b): int => \version_compare(\ltrim($a, 'vV'), \ltrim($b, 'vV')));

    return $majors;
}

/**
 * @return array<string, array<string, string>> flag name => its scalar options as written in the YAML
 */
function shopware_read_feature_flags(string $featureConfigPath = SHOPWARE_FEATURE_CONFIG): array
{
    if (!\is_file($featureConfigPath)) {
        throw new RuntimeException(\sprintf('Cannot read the feature flag registry at "%s".', $featureConfigPath));
    }

    $contents = (string) \file_get_contents($featureConfigPath);
    $flags = [];
    $current = null;

    foreach (\explode("\n", $contents) as $line) {
        if (\preg_match('/^\s*-\s+name:\s*[\'"]?([^\'"\s]+)[\'"]?\s*$/', $line, $matches)) {
            $current = $matches[1];
            $flags[$current] = [];

            continue;
        }

        // Single-token options only: `description:` values carry spaces and are of no interest here.
        if ($current !== null && \preg_match('/^\s+([A-Za-z]\w*):\s*[\'"]?([^\'"\s]+)[\'"]?\s*$/', $line, $matches)) {
            $flags[$current][$matches[1]] = $matches[2];
        }
    }

    if ($flags === []) {
        throw new RuntimeException(\sprintf('No feature flags found in "%s" — the registry format changed.', $featureConfigPath));
    }

    return $flags;
}
