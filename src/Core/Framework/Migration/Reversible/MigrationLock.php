<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Reversible;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Util\Hasher;

/**
 * Serializes migration work per plugin with a MySQL named lock.
 *
 * The lock is advisory and scoped to a single database server, so it does not serialize across the
 * nodes of a multi-primary cluster such as Galera or MySQL Group Replication.
 *
 * @internal
 */
#[Package('framework')]
class MigrationLock
{
    public function __construct(
        private readonly Connection $connection,
        private readonly int $timeoutSeconds = 60,
    ) {
    }

    /**
     * @template TReturn
     *
     * @param \Closure(): TReturn $callback
     *
     * @return TReturn
     */
    public function synchronized(string $plugin, \Closure $callback): mixed
    {
        // GET_LOCK names are limited to 64 characters, so the plugin name is hashed
        $name = self::lockName($plugin);

        $acquired = $this->connection->fetchOne(
            'SELECT GET_LOCK(:name, :timeout)',
            ['name' => $name, 'timeout' => $this->timeoutSeconds]
        );

        if ($acquired !== 1 && $acquired !== '1' && $acquired !== true) {
            throw MigrationException::migrationLockNotAcquired($plugin);
        }

        try {
            return $callback();
        } finally {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => $name]);
        }
    }

    /**
     * Exposed so tests can assert on the lock without duplicating the scheme.
     */
    public static function lockName(string $plugin): string
    {
        return 'swpm:' . Hasher::hash($plugin, 'sha1');
    }
}
