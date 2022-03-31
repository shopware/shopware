<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Kernel;

/**
 * @internal
 */
class ReplicaConnection
{
    public static function ensurePrimary(): void
    {
        $connection = Kernel::getConnection();

        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToPrimary();
        }
    }
}
