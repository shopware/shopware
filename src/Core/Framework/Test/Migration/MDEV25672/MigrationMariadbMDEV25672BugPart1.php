<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\MDEV25672;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class MigrationMariadbMDEV25672BugPart1 extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1234;
    }

    public function update(Connection $connection): void
    {
        // part 1
        $connection->exec('DROP TABLE IF EXISTS `t1`');
        $connection->exec('CREATE TABLE t1 ( a int primary key, v_a int GENERATED always AS (a));');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->update($connection);
    }
}
