<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1574258787ProductSearchLanguageKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574258787;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product_search_keyword` DROP FOREIGN KEY `fk.product_search_keyword.language_id`');
        $connection->executeUpdate('ALTER TABLE `product_search_keyword` ADD CONSTRAINT `fk.product_search_keyword.language_id` FOREIGN KEY (`language_id`)
                  REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
