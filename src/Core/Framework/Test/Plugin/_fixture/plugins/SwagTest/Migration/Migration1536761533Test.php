<?php declare(strict_types=1);

namespace SwagTest\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536761533Test extends MigrationStep
{
    public const TABLE_NAME = 'swag_test';

    public const TIMESTAMP = 1536761533;

    public function getCreationTimestamp(): int
    {
        return self::TIMESTAMP;
    }

    public function update(Connection $connection): void
    {
        $sql = '
CREATE TABLE IF NOT EXISTS %s (
    `id`   BINARY(16) NOT NULL,
    `test` VARCHAR(255) COLLATE utf8mb4_unicode_ci,
    PRIMARY KEY (`id`)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
';
        $connection->executeQuery(sprintf($sql, self::TABLE_NAME));
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
