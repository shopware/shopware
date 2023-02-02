<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1615819992AddVatIdRequiredToCountry extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1615819992;
    }

    public function update(Connection $connection): void
    {
        $featureCountryColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `country` WHERE `Field` LIKE :column;',
            ['column' => 'vat_id_required']
        );

        if ($featureCountryColumn === false) {
            $connection->executeStatement('
            ALTER TABLE `country` ADD COLUMN `vat_id_required` TINYINT (1) NOT NULL DEFAULT 0 AFTER `vat_id_pattern`;
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
