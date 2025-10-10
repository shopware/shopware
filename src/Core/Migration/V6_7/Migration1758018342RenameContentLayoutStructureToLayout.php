<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('discovery')]
class Migration1758018342RenameContentLayoutStructureToLayout extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1758018342;
    }

    public function update(Connection $connection): void
    {
        // Check if column already renamed (idempotency)
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('content_layout');

        // If 'layout' column exists, migration already ran
        if (isset($columns['layout'])) {
            return;
        }

        // Rename structure → layout
        $sql = 'ALTER TABLE `content_layout` CHANGE `structure` `layout` JSON NOT NULL';
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes
    }
}
