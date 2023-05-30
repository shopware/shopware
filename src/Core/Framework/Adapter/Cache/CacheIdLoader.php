<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

#[Package('core')]
class CacheIdLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ?CacheItemPoolInterface $restartSignalCachePool = null
    ) {
    }

    public function load(): string
    {
        $cacheId = EnvironmentHelper::getVariable('SHOPWARE_CACHE_ID');
        if ($cacheId) {
            return (string) $cacheId;
        }

        try {
            $cacheId = $this->connection->fetchOne(
                '# cache-id-loader
                SELECT `value` FROM app_config WHERE `key` = :key',
                ['key' => 'cache-id']
            );
        } catch (\Exception) {
            $cacheId = null;
        }

        if (\is_string($cacheId)) {
            return $cacheId;
        }

        $cacheId = Uuid::randomHex();

        try {
            $this->write($cacheId);

            return $cacheId;
        } catch (\Exception) {
            return 'live';
        }
    }

    public function write(string $cacheId): void
    {
        $this->connection->executeStatement(
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
