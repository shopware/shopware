<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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
     * Picks each scoped value's entry for the root source. A scoped value with no entry for it, or a null root
     * source, throws: there is no value to pick, and dropping the key would hand the loader a config nobody
     * declared. The exception names the binding specification, its `resolves` key and the element the binding is
     * applied to; `$elementId` is null when the config is collapsed without applying it to an element.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function collapse(array $config, ?string $rootSource, string $bindingSpecificationId, string $key, ?string $elementId): array
    {
        $collapsed = [];

        foreach ($config as $configKey => $value) {
            $map = self::scopeMap($value);

            if ($map === null) {
                $collapsed[$configKey] = $value;

                continue;
            }

            if ($rootSource === null || !\array_key_exists($rootSource, $map)) {
                throw ContentSystemException::bindingRootSourceNotScoped($bindingSpecificationId, $key, $elementId, $rootSource);
            }

            $collapsed[$configKey] = $map[$rootSource];
        }

        return $collapsed;
    }

    /**
     * One config per root source any scoped value names, each the {@see self::collapse()} of the config for that
     * root source. A config whose scoped values name different root-source sets throws like `collapse()`, naming
     * no element; a binding specification is validated to name one set per config, so a registered one never does.
     *
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    public static function branches(array $config, string $bindingSpecificationId, string $key): array
    {
        $rootSources = array_unique(array_merge(...array_map(
            static fn (mixed $value): array => array_keys(self::scopeMap($value) ?? []),
            array_values($config)
        )));

        if ($rootSources === []) {
            return [$config];
        }

        return array_map(
            static fn (string $rootSource): array => self::collapse($config, $rootSource, $bindingSpecificationId, $key, null),
            array_values($rootSources),
        );
    }
}
