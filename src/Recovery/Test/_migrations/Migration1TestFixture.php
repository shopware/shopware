<?php declare(strict_types=1);

namespace Shopware\Recovery\Test\_migrations;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1TestFixture extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Connection $connection): void
    {
        $connection->exec('
        CREATE TABLE test1 (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY)
');
    }

    /**
     * {@inheritdoc}
     */
    public function updateDestructive(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE test1_destructive  (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY)
        ');
    }
}
