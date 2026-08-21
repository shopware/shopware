<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1787070374AddContextHandoffToken extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1787070374;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `context_handoff_token` (
                `token` CHAR(32) NOT NULL,
                `context_token` VARCHAR(255) NOT NULL,
                `expires` DATETIME(3) NOT NULL,
                PRIMARY KEY (`token`),
                KEY `idx.context_handoff_token.expires` (`expires`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
