<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1649858046;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('
            ALTER TABLE `country`
            ADD COLUMN `postal_code_required` TINYINT (1) NOT NULL DEFAULT 0 AFTER `company_tax`,
            ADD COLUMN `check_postal_code_pattern` TINYINT (1) NOT NULL DEFAULT 0 AFTER `company_tax`,
            ADD COLUMN `check_advanced_postal_code_pattern` TINYINT (1) NOT NULL DEFAULT 0 AFTER `company_tax`,
            ADD COLUMN `advanced_postal_code_pattern` VARCHAR (255) NULL AFTER `company_tax`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
