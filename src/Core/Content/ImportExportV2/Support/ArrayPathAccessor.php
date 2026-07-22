<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Support;

use Shopware\Core\Content\ImportExportV2\ImportExportV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class ArrayPathAccessor
{
    /**
     * @param array<string, mixed> $data
     */
    public static function set(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$data;

        foreach ($segments as $index => $segment) {
            if ($segment === '*') {
                throw ImportExportV2Exception::invalidPath($path);
            }

            $segment = ctype_digit($segment) ? (int) $segment : $segment;
            $isLast = $index === \count($segments) - 1;

            if ($isLast) {
                $current[$segment] = $value;

                return;
            }

            if (!isset($current[$segment]) || !\is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $values
     */
    public static function setList(array &$data, string $path, array $values): void
    {
        [$prefix, $suffix] = array_pad(explode('.*.', $path, 2), 2, '');
        if ($prefix === '') {
            throw ImportExportV2Exception::invalidPath($path);
        }

        $items = [];
        foreach ($values as $value) {
            $item = [];

            if ($suffix === '') {
                $item = $value;
            } else {
                self::set($item, $suffix, $value);
            }

            $items[] = $item;
        }

        self::set($data, $prefix, $items);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function get(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            $segment = ctype_digit($segment) ? (int) $segment : $segment;

            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public static function getList(array $data, string $path): array
    {
        [$prefix, $suffix] = array_pad(explode('.*.', $path, 2), 2, '');
        $list = self::get($data, $prefix);
        if (!\is_array($list)) {
            return [];
        }

        $values = [];
        foreach ($list as $item) {
            if ($suffix === '') {
                $value = $item;
            } elseif (\is_array($item)) {
                $value = self::get($item, $suffix);
            } else {
                $value = null;
            }

            if ($value === null) {
                continue;
            }

            $values[] = (string) $value;
        }

        return $values;
    }
}
