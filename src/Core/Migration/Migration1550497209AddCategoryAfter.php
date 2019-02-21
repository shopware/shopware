<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550497209AddCategoryAfter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550497209;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `category`
MODIFY COLUMN `position` int(11) UNSIGNED NULL DEFAULT 1,
ADD COLUMN `after_category_id` BINARY(16) NULL AFTER `position`,
ADD COLUMN `after_category_version_id` BINARY(16) NULL AFTER `after_category_id`,
ADD CONSTRAINT `fk.category.after_category_id` FOREIGN KEY (`after_category_id`, `after_category_version_id`) 
  REFERENCES `category` (`id`, `version_id`) 
  ON DELETE SET NULL 
  ON UPDATE CASCADE 
SQL;

        $connection->exec($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        $sql = <<<SQL
ALTER TABLE `category` DROP COLUMN `position`;
SQL;

        $connection->exec($sql);
    }
}
