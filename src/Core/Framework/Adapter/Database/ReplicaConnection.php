<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[Package('core')]
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
