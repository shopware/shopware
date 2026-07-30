<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Switches a primary/replica connection back to the replica when services are
 * reset. The connection intentionally survives kernel service resets (it is
 * held statically by the Kernel), so in long running runtimes a request that
 * wrote to the primary would otherwise pin every following request handled by
 * the same worker to the primary.
 *
 * @internal
 */
#[Package('framework')]
class ReplicaConnectionResetter implements ResetInterface
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

        // An open transaction at this point is a bug in the request handling;
        // switching the connection now would silently discard it.
        if ($this->connection->getTransactionNestingLevel() > 0) {
            return;
        }

        if (!$this->connection->isConnectedToPrimary()) {
            return;
        }

        $this->connection->ensureConnectedToReplica();
    }
}
