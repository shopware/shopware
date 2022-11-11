<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\CacheItem;

/**
 * @package core
 * @template TCachedContent
 */
class CacheCompressor
{
    /**
     * @param TCachedContent $content
     */
    public static function compress(CacheItem $item, $content): CacheItem
    {
        $item->set(CacheValueCompressor::compress($content));

        return $item;
    }

    /**
     * @return TCachedContent
     */
    public static function uncompress(CacheItem $item)
    {
        /** @var TCachedContent|string $value */
        $value = $item->get();

        return CacheValueCompressor::uncompress($value);
    }
}
