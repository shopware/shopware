<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\CacheItem;

/**
 * @template TCachedContent
 */
class CacheCompressor
{
    /**
     * @param TCachedContent $content
     */
    public static function compress(CacheItem $item, $content): CacheItem
    {
        $item->set(gzcompress(serialize($content), 9));

        return $item;
    }

    /**
     * @return TCachedContent
     */
    public static function uncompress(CacheItem $item)
    {
        /** @var TCachedContent|string $value */
        $value = $item->get();

        if (!\is_string($value)) {
            return $value;
        }

        $uncompressed = gzuncompress($value);
        if ($uncompressed === false) {
            throw new \RuntimeException(sprintf('Could not uncompress "%s"', $value));
        }

        return unserialize($uncompressed);
    }
}
