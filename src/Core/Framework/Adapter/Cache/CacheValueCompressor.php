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
    /**
     * Magic number every zstd frame starts with.
     */
    private const ZSTD_MAGIC_NUMBER = "\x28\xB5\x2F\xFD";

    /**
     * zlib header CMF byte for deflate with a 32 KiB window, which `gzcompress()` always writes.
     */
    private const ZLIB_DEFLATE_CMF = "\x78";

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
            self::isZstd($value) => self::zstdUncompress($value),
            self::isZlib($value) => \gzuncompress($value),
            self::isSerialized($value) => $value,
            default => throw AdapterException::cacheCompressionError('Unknown cache value format'),
        };

        if ($uncompressed === false) {
            throw AdapterException::cacheCompressionError('Could not uncompress value');
        }

        /** @phpstan-ignore shopware.unserializeUsage */
        return \unserialize($uncompressed);
    }

    private static function zstdUncompress(string $value): string|false
    {
        if (!\function_exists('zstd_uncompress')) {
            throw AdapterException::cacheCompressionError('Value is zstd compressed, but the zstd PHP extension is not loaded');
        }

        return \zstd_uncompress($value);
    }

    private static function isZstd(string $value): bool
    {
        return str_starts_with($value, self::ZSTD_MAGIC_NUMBER);
    }

    /**
     * RFC 1950: the two header bytes CMF and FLG, read as a 16-bit number, are a multiple of 31.
     */
    private static function isZlib(string $value): bool
    {
        if (\strlen($value) < 2 || $value[0] !== self::ZLIB_DEFLATE_CMF) {
            return false;
        }

        return ((\ord($value[0]) << 8) + \ord($value[1])) % 31 === 0;
    }

    /**
     * Matches a value written by `serialize()`: `N;` for null, otherwise a type letter followed by a colon.
     */
    private static function isSerialized(string $value): bool
    {
        return $value === 'N;' || ($value[1] ?? '') === ':';
    }
}
