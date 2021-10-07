<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedLanguageLoader implements LanguageLoaderInterface, EventSubscriberInterface
{
    private const CACHE_KEY = 'shopware.languages';

    private CacheItemPoolInterface $cache;

    private LanguageLoaderInterface $loader;

    public function __construct(LanguageLoaderInterface $loader, CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
        $this->loader = $loader;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LanguageEvents::LANGUAGE_DELETED_EVENT => 'invalidateCache',
            LanguageEvents::LANGUAGE_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    public function loadLanguages(): array
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $languages = $this->loader->loadLanguages();

        $cacheItem->set($languages);
        $this->cache->save($cacheItem);

        return $languages;
    }

    public function invalidateCache(): void
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if (!$cacheItem->isHit()) {
            return;
        }
        $this->cache->deleteItem($cacheItem->getKey());
    }
}
