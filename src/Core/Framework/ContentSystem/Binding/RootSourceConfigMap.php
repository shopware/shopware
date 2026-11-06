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
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public static function collapse(array $config, ?string $rootSource): array
    {
        $collapsed = [];

        foreach ($config as $key => $value) {
            $map = self::scopeMap($value);

            if ($map === null) {
                $collapsed[$key] = $value;

                continue;
            }

            if ($rootSource !== null && \array_key_exists($rootSource, $map)) {
                $collapsed[$key] = $map[$rootSource];
            }
        }

        return $collapsed;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<array<string, mixed>>
     */
    public static function branches(array $config): array
    {
        $rootSources = array_unique(array_merge(...array_map(
            static fn (mixed $value): array => array_keys(self::scopeMap($value) ?? []),
            array_values($config)
        )));

        if ($rootSources === []) {
            return [$config];
        }

        return array_map(
            static fn (string $rootSource): array => self::collapse($config, $rootSource),
            array_values($rootSources),
        );
    }
}
