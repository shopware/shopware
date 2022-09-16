<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1663322402AddMarginResponsiveColumnToCmsBlock extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1663322402;
    }

    public function update(Connection $connection): void
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `cms_block` WHERE `Field` LIKE :column;',
            ['column' => 'margin_responsive']
        );

        if (!empty($field)) {
            return;
        }

        $connection->executeStatement('ALTER TABLE `cms_block` ADD `margin_responsive` JSON default NULL AFTER `margin_right` ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
