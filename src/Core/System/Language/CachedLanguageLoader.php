<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 *
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
class CachedLanguageLoader implements LanguageLoaderInterface, EventSubscriberInterface
{
    private const CACHE_KEY = 'shopware.languages';

    private CacheInterface $cache;

    private LanguageLoaderInterface $loader;

    /**
     * @internal
     */
    public function __construct(LanguageLoaderInterface $loader, CacheInterface $cache)
    {
        $this->cache = $cache;
        $this->loader = $loader;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LanguageEvents::LANGUAGE_DELETED_EVENT => 'invalidateCache',
            LanguageEvents::LANGUAGE_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @return LanguageData
     */
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
