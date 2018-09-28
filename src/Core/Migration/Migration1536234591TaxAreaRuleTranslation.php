<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234591TaxAreaRuleTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234591;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `tax_area_rule_translation` (
              `tax_area_rule_id` binary(16) NOT NULL,
              `tax_area_rule_version_id` binary(16) NOT NULL,
              `tax_area_rule_tenant_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`tax_area_rule_id`, `tax_area_rule_version_id`, `tax_area_rule_tenant_id`,`language_id`, `language_tenant_id`),
              CONSTRAINT `tax_area_rule_translation_ibfk_1` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `tax_area_rule_translation_ibfk_2` FOREIGN KEY (`tax_area_rule_id`, `tax_area_rule_version_id`, `tax_area_rule_tenant_id`) REFERENCES `tax_area_rule` (`id`, `version_id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
