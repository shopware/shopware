<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

class CacheEvents
{
    /**
     * @Event("Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent")
     */
    public const CACHE_GENERATE_KEY_EVENT = 'cache.generate_key';
}