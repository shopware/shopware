<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536239251ProductMediaConstraint extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536239251;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `product_media`
            ADD CONSTRAINT `fk_product_media.product_id`
            FOREIGN KEY (`product_id`, `product_version_id`, `product_tenant_id`) 
            REFERENCES `product` (`id`, `version_id`, `tenant_id`)
            ON DELETE CASCADE ON UPDATE CASCADE;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
