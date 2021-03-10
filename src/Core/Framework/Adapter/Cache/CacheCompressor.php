<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\CacheItem;

class CacheCompressor
{
    public static function compress(CacheItem $item, $content): CacheItem
    {
        $item->set(gzcompress(serialize($content), 9));

        return $item;
    }

    public static function uncompress(CacheItem $item)
    {
        $value = $item->get();

        if (!\is_string($value)) {
            return $value;
        }

        return unserialize(gzuncompress($value));
    }
}
