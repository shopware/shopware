<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
class Migration1775200001IncreaseProductDisplayGroupLength extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1775200001;
    }

    public function update(Connection $connection): void
    {
        if (!$this->isDisplayGroupLength50($connection)) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');
    }

    private function isDisplayGroupLength50(Connection $connection): bool
    {
        $columnType = $connection->fetchOne(
            <<<'SQL'
            SELECT LOWER(COLUMN_TYPE)
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = 'product'
              AND column_name = 'display_group';
            SQL,
            ['schema' => $connection->getDatabase()]
        );

        return \is_string($columnType) && $columnType === 'varchar(50)';
    }
}
