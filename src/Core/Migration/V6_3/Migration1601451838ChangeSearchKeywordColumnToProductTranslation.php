<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1601451838ChangeSearchKeywordColumnToProductTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1601451838;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product_translation` DROP COLUMN `search_keywords`;');

        $connection->executeStatement('
            ALTER TABLE `product_translation`
            ADD COLUMN `custom_search_keywords` JSON NULL,
            ADD CONSTRAINT `json.product_translation.custom_search_keywords` CHECK (JSON_VALID(`custom_search_keywords`));
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
