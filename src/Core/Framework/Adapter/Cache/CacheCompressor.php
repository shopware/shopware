<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\CacheItem;

/**
 * @template TCachedContent
 */
#[Package('core')]
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
