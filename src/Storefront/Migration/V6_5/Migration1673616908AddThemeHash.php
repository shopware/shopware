<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @package core
 *
 * @internal
 */
class Migration1673616908AddThemeHash extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1673616908;
    }

    public function update(Connection $connection): void
    {
        if ($this->columnExists($connection, 'theme_sales_channel', 'hash')) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `theme_sales_channel` ADD `hash` VARCHAR(255) NULL');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
