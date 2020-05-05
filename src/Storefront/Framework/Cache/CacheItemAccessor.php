<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;

class CacheItemAccessor implements ItemInterface
{
    public function getKey()
    {

    }

    public function get()
    {

    }

    public function isHit()
    {

    }

    public function set($value)
    {

    }

    public function expiresAt($expiration)
    {

    }

    public function expiresAfter($time)
    {

    }

    public function tag($tags): ItemInterface
    {

    }

    public function getMetadata(): array
    {

    }

    public static function getTags(CacheItem $item)
    {
        dd($item->newMetadata);
    }
}
