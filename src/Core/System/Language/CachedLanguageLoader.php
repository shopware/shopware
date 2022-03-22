<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CachedLanguageLoader implements LanguageLoaderInterface, EventSubscriberInterface
{
    private const CACHE_KEY = 'shopware.languages';

    private CacheInterface $cache;

    private LanguageLoaderInterface $loader;

    public function __construct(LanguageLoaderInterface $loader, CacheInterface $cache)
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
        return $this->cache->get(self::CACHE_KEY, function () {
            return $this->loader->loadLanguages();
        });
    }

    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
