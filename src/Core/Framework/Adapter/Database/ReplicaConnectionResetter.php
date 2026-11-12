<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetter
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        if (!$this->connection instanceof PrimaryReadReplicaConnection) {
            return;
        }

        // switching during an open transaction would silently discard it
        if ($this->connection->getTransactionNestingLevel() > 0) {
            return;
        }

        if (!$this->connection->isConnectedToPrimary()) {
            return;
        }

        $this->connection->ensureConnectedToReplica();
    }
}
