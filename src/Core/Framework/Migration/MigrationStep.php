<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;

abstract class MigrationStep
{
    /**
     * get creation time stamp
     */
    public function getCreationTimeStamp(): int
    {
        return 1;
    }

    /**
     * update non-destructive changes
     */
    public function update(Connection $connection)
    {
    }

    /**
     * update destructive changes
     */
    public function updateDestructive(Connection $connection)
    {
    }
}
