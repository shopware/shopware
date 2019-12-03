<?php declare(strict_types=1);

namespace Shopware\Storefront\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1574258786SeoUrlLanguageKeyCascade extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1574258786;
    }

    public function update(Connection $connection): void
    {
        $connection->executeUpdate('ALTER TABLE `seo_url` DROP FOREIGN KEY `fk.seo_url.language_id`');
        $connection->executeUpdate('ALTER TABLE `seo_url` ADD CONSTRAINT `fk.seo_url.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
