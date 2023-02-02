<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Doctrine\DBAL\Connection;

trait StateMachineMigrationTrait
{
    private function import(StateMachineMigration $migration, Connection $connection): StateMachineMigration
    {
        return (new StateMachineMigrationImporter($connection))->importStateMachine($migration);
    }
}
