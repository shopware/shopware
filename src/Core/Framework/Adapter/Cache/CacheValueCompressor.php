<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TCachedContent
 */
#[Package('framework')]
class CacheValueCompressor
{
    public static bool $compress = true;

    public static string $compressMethod = 'gzip';

    /**
     * @param TCachedContent $content
     */
    public static function compress($content): string
    {
        if (!self::$compress) {
            return \serialize($content);
        }

        if (self::$compressMethod === 'zstd') {
            $compressed = \zstd_compress(\serialize($content));
        } elseif (self::$compressMethod === 'gzip') {
            $compressed = \gzcompress(\serialize($content));
        } else {
            throw FrameworkException::invalidCompressionMethod(self::$compressMethod);
        }

        if ($compressed === false) {
            throw AdapterException::cacheCompressionError('Failed to compress cache value');
        }

        return $compressed;
    }

    /**
     * @param TCachedContent|string $value
     *
     * @return TCachedContent
     */
    public static function uncompress($value)
    {
        if (!\is_string($value)) {
            return $value;
        }

        $uncompressed = match (true) {
            self::isZstd($value) => \zstd_uncompress($value),
            self::isZlib($value) => \gzuncompress($value),
            default => $value,
        };

        if ($uncompressed === false) {
            throw AdapterException::cacheCompressionError('Could not uncompress value');
        }

        /** @phpstan-ignore shopware.unserializeUsage */
        return \unserialize($uncompressed);
    }

    private static function isZstd(string $value): bool
    {
        return str_starts_with($value, "\x28\xB5\x2F\xFD");
    }

    private static function isZlib(string $value): bool
    {
        if (\strlen($value) < 2 || $value[0] !== "\x78") {
            return false;
        }

        return ((\ord($value[0]) << 8) + \ord($value[1])) % 31 === 0;
    }
}
