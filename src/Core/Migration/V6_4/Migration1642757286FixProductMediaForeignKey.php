<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - Migrations will be internal in v6.5.0
 */
class Migration1642757286FixProductMediaForeignKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1642757286;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product` DROP FOREIGN KEY `fk.product.product_media_id`
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
