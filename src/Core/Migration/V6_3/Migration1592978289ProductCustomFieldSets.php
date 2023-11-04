<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1592978289ProductCustomFieldSets extends MigrationStep
{
    use InheritanceUpdaterTrait;

    public function getCreationTimestamp(): int
    {
        return 1592978289;
    }

    public function update(Connection $connection): void
    {
        $this->createAssociation($connection);
        $this->addGlobalFlag($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    /**
     * @throws Exception
     */
    private function createAssociation(Connection $connection): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `product_custom_field_set` (
    `custom_field_set_id` BINARY(16) NOT NULL,
    `product_id`             BINARY(16) NOT NULL,
    `product_version_id`     BINARY(16) NOT NULL,
    PRIMARY KEY (`custom_field_set_id`, `product_id`, `product_version_id`),
    CONSTRAINT `fk.product_custom_field_set.custom_field_set_id` FOREIGN KEY (`custom_field_set_id`)
        REFERENCES `custom_field_set` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk.product_custom_field_set.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
        REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);

        $this->updateInheritance($connection, 'product', 'customFieldSets');
    }

    /**
     * @throws Exception
     */
    private function addGlobalFlag(Connection $connection): void
    {
        $sql = <<<'SQL'
ALTER TABLE `custom_field_set`
ADD `global` tinyint(1) NOT NULL DEFAULT 0 AFTER `active`;
SQL;
        $connection->executeStatement($sql);
    }
}
