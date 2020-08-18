<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

class CacheIdLoader
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CacheItemPoolInterface|null
     */
    private $restartSignalCachePool;

    public function __construct(Connection $connection, ?CacheItemPoolInterface $restartSignalCachePool = null)
    {
        $this->connection = $connection;
        $this->restartSignalCachePool = $restartSignalCachePool;
    }

    public function load(): string
    {
        try {
            $cacheId = $this->connection->fetchColumn(
                '# cache-id-loader
                SELECT `value` FROM app_config WHERE `key` = :key',
                ['key' => 'cache-id']
            );
        } catch (\Exception $e) {
            $cacheId = null;
        }

        if (is_string($cacheId)) {
            return $cacheId;
        }

        $cacheId = Uuid::randomHex();

        try {
            $this->write($cacheId);

            return $cacheId;
        } catch (\Exception $e) {
            return 'live';
        }
    }

    public function write(string $cacheId): void
    {
        $this->connection->executeUpdate(
            'REPLACE INTO app_config (`key`, `value`) VALUES (:key, :cacheId)',
            ['cacheId' => $cacheId, 'key' => 'cache-id']
        );

        if ($this->restartSignalCachePool) {
            $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
            $cacheItem->set(microtime(true));
            $this->restartSignalCachePool->save($cacheItem);
        }
    }
}
