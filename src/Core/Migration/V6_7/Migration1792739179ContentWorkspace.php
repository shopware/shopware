<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1792739179ContentWorkspace extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1792739179;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `content_workspace` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `configuration` JSON NOT NULL,
                `created_by_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.content_workspace.created_by_id` FOREIGN KEY (`created_by_id`)
                    REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);

        $this->addAdditionalPrivileges($connection, [
            'experience_studio.viewer' => ['content_workspace:read'],
            'experience_studio.editor' => ['content_workspace:create', 'content_workspace:update'],
            'experience_studio.deleter' => ['content_workspace:delete'],
        ]);
    }
}
