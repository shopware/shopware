<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1664512574AddConfigShowHideSectionBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1664512574;
    }

    public function update(Connection $connection): void
    {
        $this->updateSchema($connection, 'cms_section');
        $this->updateSchema($connection, 'cms_block');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function updateSchema(Connection $connection, string $tableName): void
    {
        if (!$this->columnExists($connection, $tableName, 'visibility')) {
            $connection->executeStatement(\sprintf('ALTER TABLE `%s` ADD COLUMN `visibility` JSON NULL AFTER `background_media_mode`', $tableName));
        }
    }
}
