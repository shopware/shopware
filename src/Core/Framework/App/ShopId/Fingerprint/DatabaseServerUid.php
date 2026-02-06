<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId\Fingerprint;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\App\ShopId\Fingerprint;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore covered by Integration test: \Shopware\Tests\Integration\Core\Framework\App\ShopId\Fingerprint\DatabaseServerUidTest
 */
#[Package('framework')]
readonly class DatabaseServerUid implements Fingerprint
{
    final public const IDENTIFIER = 'database_server_uid';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Changes to a new DB server or a new table space are a near certain indicator, that this is a different instance.
     */
    public function getScore(): int
    {
        return 100;
    }

    public function getStamp(): string
    {
        return $this->getDBServerUid() . $this->getTablespaceName();
    }

    private function getDBServerUid(): string
    {
        if ($this->connection instanceof PrimaryReadReplicaConnection) {
            $this->connection->ensureConnectedToPrimary();
        }

        // MySQL uuid variable: https://dev.mysql.com/doc/refman/8.4/en/replication-options.html#sysvar_server_uuid
        $serverId = $this->connection->fetchAssociative('SHOW VARIABLES WHERE Variable_name = "server_uuid"')['Value'] ?? '';

        if ($serverId) {
            return (string) $serverId;
        }

        // MariaDB uuid variable: https://mariadb.com/docs/server/server-management/variables-and-modes/server-system-variables#server_uid
        $serverId = $this->connection->fetchAssociative('SHOW VARIABLES WHERE Variable_name = "server_uid"')['Value'] ?? '';

        if ($serverId) {
            return (string) $serverId;
        }

        // older maria DB versions do not have the server_uid variable
        return '';
    }

    private function getTablespaceName(): string
    {
        return (string) $this->connection->fetchOne('SELECT database()');
    }
}
