<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration2 extends MigrationStep
{
    public function getCreationTimeStamp(): int
    {
        return 22;
    }

    public function update(Connection $connection)
    {
        throw new \Exception('update');
    }

    public function updateDestructive(Connection $connection)
    {
        //nth
    }
}
