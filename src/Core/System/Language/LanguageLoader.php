<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LanguageLoader implements LanguageLoaderInterface, EventSubscriberInterface
{
    private const CACHE_KEY = 'shopware.languages';
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    public function __construct(Connection $connection, CacheItemPoolInterface $cache)
    {
        $this->connection = $connection;
        $this->cache = $cache;
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

        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.id)) AS id, locale.code, LOWER(HEX(language.parent_id)) parentId'])
            ->from('language')
            ->leftJoin('language', 'locale', 'locale', 'language.translation_code_id = locale.id')
            ->execute()
            ->fetchAll();

        $languages = [];
        foreach ($data as $row) {
            $languages[$row['id']] = $row;
            if ($row['code']) {
                $languages[strtolower($row['code'])] = $row;
            }
        }
        $cacheItem->set($languages);

        return $languages;
    }

    /**
     * @internal should only be called in response to a language update and delete event
     */
    public function invalidateCache(): void
    {
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if (!$cacheItem->isHit()) {
            return;
        }
        $this->cache->deleteItem($cacheItem->getKey());
    }
}
