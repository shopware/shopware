<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1537516116RemoveCountryArea extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1537516116;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeQuery('
            ALTER TABLE `country`
            DROP COLUMN `country_area_id`,
            DROP COLUMN `country_area_tenant_id`,
            DROP COLUMN `country_area_version_id`,
            DROP FOREIGN KEY `fk_area_country.country_area_id`
        ');
        $connection->executeQuery('
            DROP TABLE `tax_area_rule_translation`
        ');
        $connection->executeQuery('
            DROP TABLE `tax_area_rule`
        ');
        $connection->executeQuery('
            DROP TABLE `country_area_translation`
        ');
        $connection->executeQuery('
            DROP TABLE `country_area`
        ');
    }
}
