<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TCachedContent
 */
#[Package('core')]
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
            $compressed = \gzcompress(\serialize($content), 9);
        } else {
            throw FrameworkException::invalidCompressionMethod(self::$compressMethod);
        }

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress cache value');
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
            return \unserialize($value);
        }

        if (self::$compressMethod === 'zstd') {
            $uncompressed = \zstd_uncompress($value);
        } elseif (self::$compressMethod === 'gzip') {
            $uncompressed = \gzuncompress($value);
        } else {
            throw FrameworkException::invalidCompressionMethod(self::$compressMethod);
        }

        if ($uncompressed === false) {
            throw new \RuntimeException(sprintf('Could not uncompress "%s"', $value));
        }

        return unserialize($uncompressed);
    }
}
