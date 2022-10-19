<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1563949275AddCompanyToOrderCustomer extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1563949275;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `order_customer` ADD `company` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL AFTER `title`');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
