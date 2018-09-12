<?php declare(strict_types=1);

namespace Shopware\Core\Version;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536234595SearchDictionary extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536234595;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `search_dictionary` (
              `tenant_id` binary(16) NOT NULL,
              `scope` varchar(100) NOT NULL,
              `keyword` varchar(500) NOT NULL,
              `reversed` varchar(500) NOT NULL,
              `version_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `language_tenant_id` binary(16) NOT NULL,
              PRIMARY KEY `language_keyword` (`keyword`, `scope`, `language_id`, `version_id`, `tenant_id`, `language_tenant_id`),
              CONSTRAINT `fk_search_dictionary.language_id` FOREIGN KEY (`language_id`, `language_tenant_id`) REFERENCES `language` (`id`, `tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
              INDEX `scope_language_id` (`scope`, `language_id`, `tenant_id`)
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
