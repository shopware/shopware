<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('core')]
class Migration1586260286AddProductMainVariant extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1586260286;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            ALTER TABLE `product`
            ADD `main_variant_id` BINARY(16) NULL
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
