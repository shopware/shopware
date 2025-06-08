<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1720610755Foo extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1720610755;
    }

    public function update(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'test_table', 'column');
        $this->dropForeignKeyIfExists($connection, 'test_table', 'foreign_key');
        $this->dropTableIfExists($connection, 'test_table');
        $this->doFoo($connection);

        $sql = 'alter table test_table drop column column_name;';
        $connection->executeStatement($sql);
        $connection->executeStatement('ALTER TABLE `test_table` DROP COLUMN `column`;');
        $connection->executeStatement('ALTER TABLE `test_table` DROP `column`;');
        $connection->executeStatement('ALTER TABLE `test_table` DROP FOREIGN KEY `foreign_key`;');
        $connection->executeStatement('DROP TABLE `test_table`;');

        // is allowed
        $this->dropIndexIfExists($connection, 'test_table', 'index');
        $connection->executeStatement('ALTER TABLE `test_table` ADD COLUMN `column` VARCHAR(255);');
        $connection->executeStatement('ALTER TABLE `test_table` DROP INDEX `index`;');
        $connection->executeQuery('SELECT * FROM `table_name`;');
    }

    public function updateDestructive(Connection $connection): void
    {
        // everything is allowed here
        $this->dropColumnIfExists($connection, 'test_table', 'column');
        $this->dropForeignKeyIfExists($connection, 'test_table', 'foreign_key');
        $this->dropTableIfExists($connection, 'test_table');
        $this->doFoo($connection);

        $sql = 'alter table test_table drop column column_name;';
        $connection->executeStatement($sql);
        $connection->executeStatement('ALTER TABLE `test_table` DROP COLUMN `column`;');
        $connection->executeStatement('ALTER TABLE `test_table` DROP `column`;');
        $connection->executeStatement('ALTER TABLE `test_table` DROP FOREIGN KEY `foreign_key`;');
        $connection->executeStatement('DROP TABLE `test_table`;');

        $this->dropIndexIfExists($connection, 'test_table', 'index');
        $connection->executeStatement('ALTER TABLE `test_table` ADD COLUMN `column` VARCHAR(255);');
        $connection->executeStatement('ALTER TABLE `test_table` DROP INDEX `index`;');
        $connection->executeQuery('SELECT * FROM `table_name`;');
    }

    private function doFoo(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'test_table', 'column');
        $connection->executeStatement('ALTER TABLE `test_table` DROP COLUMN `column`;');

        $this->doBar($connection);
    }

    private function doBar(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'test_table', 'column');
    }
}
