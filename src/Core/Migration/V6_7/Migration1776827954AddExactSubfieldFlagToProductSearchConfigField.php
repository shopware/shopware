<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('inventory')]
class Migration1776827954AddExactSubfieldFlagToProductSearchConfigField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1776827954;
    }

    public function update(Connection $connection): void
    {
        if (!$this->columnExists($connection, 'product_search_config_field', 'use_exact_subfield')) {
            $connection->executeStatement('
                ALTER TABLE `product_search_config_field`
                ADD COLUMN `use_exact_subfield` TINYINT(1) NOT NULL DEFAULT 0 AFTER `searchable`
            ');
        }

        $connection->executeStatement(
            'UPDATE `product_search_config_field`
                SET `use_exact_subfield` = 1
              WHERE `field` IN (:fields)',
            ['fields' => ['name', 'customSearchKeywords']],
            ['fields' => ArrayParameterType::STRING]
        );
    }
}
