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
     * igbinary serialized payloads always start with this 4-byte version header, which native
     * serialize() output never produces (it always starts with a type char such as "a:"/"O:").
     */
    private const IGBINARY_HEADER = "\x00\x00\x00\x02";

    public static bool $compress = true;

    public static string $compressMethod = 'gzip';

    /**
     * Serialization method used for writing cache values. Reads auto-detect the format, so values
     * written with a different method (e.g. during a rolling deploy or after a config change) stay
     * decodable without a cache flush. Defaults to native `serialize` to preserve behavior.
     */
    public static string $serializeMethod = 'serialize';

    /**
     * @param TCachedContent $content
     */
    public static function compress($content): string
    {
        $serialized = self::serialize($content);

        if (!self::$compress) {
            return $serialized;
        }

        if (self::$compressMethod === 'zstd') {
            $compressed = \zstd_compress($serialized);
        } elseif (self::$compressMethod === 'gzip') {
            $compressed = \gzcompress($serialized);
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

        if (!self::$compress) {
            return self::unserialize($value);
        }

        if (self::$compressMethod === 'zstd') {
            $uncompressed = \zstd_uncompress($value);
        } elseif (self::$compressMethod === 'gzip') {
            $uncompressed = \gzuncompress($value);
        } else {
            throw FrameworkException::invalidCompressionMethod(self::$compressMethod);
        }

        if ($uncompressed === false) {
            throw AdapterException::cacheCompressionError('Could not uncompress value');
        }

        return self::unserialize($uncompressed);
    }

    /**
     * @param TCachedContent $content
     */
    private static function serialize($content): string
    {
        if (self::$serializeMethod === 'igbinary' && \function_exists('igbinary_serialize')) {
            $serialized = \igbinary_serialize($content);

            if (!\is_string($serialized)) {
                throw AdapterException::cacheCompressionError('Failed to serialize cache value');
            }

            return $serialized;
        }

        return \serialize($content);
    }

    /**
     * @return TCachedContent
     */
    private static function unserialize(string $value)
    {
        if (\function_exists('igbinary_unserialize') && str_starts_with($value, self::IGBINARY_HEADER)) {
            return \igbinary_unserialize($value);
        }

        /** @phpstan-ignore shopware.unserializeUsage */
        return \unserialize($value);
    }
}
