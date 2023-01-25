<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1559306391PromotionIndividualCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1559306391;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `promotion_individual_code` (
              `id` BINARY(16) NOT NULL,
              `promotion_id` BINARY(16) NOT NULL,
              `code` VARCHAR(255) NULL UNIQUE,
              `payload` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              INDEX `idx.promotion_individual_code.promotion_id` (`promotion_id` ASC),
              CONSTRAINT `fk.promotion_individual_code.promotion_id` FOREIGN KEY (`promotion_id`)
                REFERENCES `promotion` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
