<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1574258787ProductSearchLanguageKey extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574258787;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product_search_keyword` DROP FOREIGN KEY `fk.product_search_keyword.language_id`');
        $connection->executeStatement('ALTER TABLE `product_search_keyword` ADD CONSTRAINT `fk.product_search_keyword.language_id` FOREIGN KEY (`language_id`)
                  REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
