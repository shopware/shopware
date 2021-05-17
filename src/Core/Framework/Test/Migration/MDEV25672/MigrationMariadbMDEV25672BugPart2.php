<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\MDEV25672;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class MigrationMariadbMDEV25672BugPart2 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1235;
    }

    public function update(Connection $connection): void
    {
        // part 2
        $connection->fetchAllAssociative('SELECT 1 FROM t1 as x;');
        $connection->exec('ALTER TABLE t1 force');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->update($connection);
    }
}
