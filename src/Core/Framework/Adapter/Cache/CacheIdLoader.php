<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class CacheIdLoader
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(): string
    {
        try {
            $cacheId = $this->connection->fetchColumn(
                'SELECT `value` FROM app_config WHERE `key` = :key',
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
    }
}
