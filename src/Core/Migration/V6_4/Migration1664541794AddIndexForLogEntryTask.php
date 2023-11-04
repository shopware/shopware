<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1664541794AddIndexForLogEntryTask extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664541794;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement('ALTER TABLE `log_entry` ADD INDEX `idx.log_entry.created_at` (`created_at`)');
        } catch (\Exception) {
            // index already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
