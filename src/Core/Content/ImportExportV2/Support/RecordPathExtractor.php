<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Support;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class RecordPathExtractor
{
    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public static function extract(array $data): array
    {
        $paths = [];
        self::extractPaths($data, '', $paths);

        $normalized = array_map(
            static fn (string $path): string => preg_replace('/\.\d+(?=\.|$)/', '.*', $path) ?? $path,
            $paths
        );

        $uniquePaths = array_values(array_unique($normalized));
        sort($uniquePaths);

        return $uniquePaths;
    }

    /**
     * @param array<string|int, mixed> $data
     * @param list<string> $paths
     */
    private static function extractPaths(array $data, string $prefix, array &$paths): void
    {
        foreach ($data as $key => $value) {
            $segment = \is_int($key) ? '*' : (string) $key;
            $path = $prefix === '' ? $segment : $prefix . '.' . $segment;

            if (\is_array($value) && $value !== []) {
                self::extractPaths($value, $path, $paths);

                continue;
            }

            $paths[] = $path;
        }
    }
}
