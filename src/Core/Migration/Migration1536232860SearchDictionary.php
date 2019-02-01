<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232860SearchDictionary extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232860;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `search_dictionary` (
              `scope` VARCHAR(100) NOT NULL,
              `keyword` VARCHAR(500) NOT NULL,
              `reversed` VARCHAR(500) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`keyword`, `scope`, `language_id`),
              CONSTRAINT `fk.search_dictionary.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              INDEX `idx.scope_language_id` (`scope`, `language_id`)
            );
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
