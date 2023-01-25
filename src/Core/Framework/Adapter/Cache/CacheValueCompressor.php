<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;

/**
 * @template TCachedContent
 */
#[Package('core')]
class CacheValueCompressor
{
    public static bool $compress = true;

    /**
     * @param TCachedContent $content
     */
    public static function compress($content): string
    {
        if (!self::$compress) {
            return \serialize($content);
        }

        $compressed = gzcompress(serialize($content), 9);

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

        $uncompressed = gzuncompress($value);
        if ($uncompressed === false) {
            throw new \RuntimeException(sprintf('Could not uncompress "%s"', $value));
        }

        return unserialize($uncompressed);
    }
}
