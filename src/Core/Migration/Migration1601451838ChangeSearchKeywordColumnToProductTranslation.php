<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1601451838ChangeSearchKeywordColumnToProductTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601451838;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `product_translation` DROP COLUMN `search_keywords`;');

        $connection->executeUpdate('
            ALTER TABLE `product_translation`
            ADD COLUMN `custom_search_keywords` JSON NULL,
            ADD CONSTRAINT `json.product_translation.custom_search_keywords` CHECK (JSON_VALID(`custom_search_keywords`));
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
