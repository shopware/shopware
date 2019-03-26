<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1553516887CleanUpCategory extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1553516887;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->exec(
            'ALTER TABLE `category`
            DROP COLUMN `template`,
            DROP COLUMN `is_blog`,
            DROP COLUMN `external`,
            DROP COLUMN `hide_filter`,
            DROP COLUMN `hide_top`,
            DROP COLUMN `product_box_layout`,
            DROP COLUMN `hide_sortings`,
            DROP COLUMN `sorting_ids`,
            DROP COLUMN `facet_ids`
            ;'
        );

        $connection->exec(
            'ALTER TABLE `category_translation`
            DROP COLUMN `meta_keywords`,
            DROP COLUMN `meta_title`,
            DROP COLUMN `meta_description`,
            DROP COLUMN `cms_headline`,
            DROP COLUMN `cms_description`
            ;'
        );
    }
}
