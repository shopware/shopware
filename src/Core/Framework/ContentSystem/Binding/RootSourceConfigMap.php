<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class RootSourceConfigMap
{
    public const TAG = 'scoped';

    public const MARKER = '$scoped';

    /**
     * @return array<string, mixed>|null
     */
    public static function scopeMap(mixed $value): ?array
    {
        if (!\is_array($value) || \count($value) !== 1 || !\array_key_exists(self::MARKER, $value)) {
            return null;
        }

        $map = $value[self::MARKER];

        return \is_array($map) ? $map : null;
    }

    public static function isScoped(mixed $value): bool
    {
        return self::scopeMap($value) !== null;
    }

    /**
     * The config with each scoped value replaced by its entry for the root source, or null when a scoped value has
     * no entry for it. A null root source has no entry in any scoped map. A config without a scoped value is
     * returned unchanged for every root source, null included. A null return leaves the reaction to the caller:
     * there is no value to pick, and dropping the key would hand the loader a config nobody declared.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>|null
     */
    public static function collapse(array $config, ?string $rootSource): ?array
    {
        $collapsed = [];

        foreach ($config as $configKey => $value) {
            $map = self::scopeMap($value);

            if ($map === null) {
                $collapsed[$configKey] = $value;

                continue;
            }

            if ($rootSource === null || !\array_key_exists($rootSource, $map)) {
                return null;
            }

            $collapsed[$configKey] = $map[$rootSource];
        }

        return $collapsed;
    }

    /**
     * One config per root source any scoped value names, each the {@see self::collapse()} of the config for that
     * root source, in the order the scoped values first name them. A config whose scoped values are all empty, or
     * that has none, is its own single branch. Null when the scoped values name different root-source sets, so that
     * some root source has no entry in one of them; a binding specification is validated to name one set per
     * config, so a registered one never does.
     *
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>|null
     */
    public static function branches(array $config): ?array
    {
        $rootSources = array_unique(array_merge(...array_map(
            static fn (mixed $value): array => array_keys(self::scopeMap($value) ?? []),
            array_values($config)
        )));

        if ($rootSources === []) {
            return [$config];
        }

        $branches = [];

        foreach ($rootSources as $rootSource) {
            $branch = self::collapse($config, (string) $rootSource);

            if ($branch === null) {
                return null;
            }

            $branches[] = $branch;
        }

        return $branches;
    }
}
