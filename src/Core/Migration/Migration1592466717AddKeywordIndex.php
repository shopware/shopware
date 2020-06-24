<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1592466717AddKeywordIndex extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1592466717;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeUpdate('ALTER TABLE `product_search_keyword` ADD INDEX `idx.product_search_keyword.keyword_language` (`keyword`, `language_id`);');
        } catch (DBALException $e) {
            // index already exists
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
