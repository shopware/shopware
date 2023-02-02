<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1645453538AddRuleTag extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1645453538;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `rule_tag` (
              `rule_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`rule_id`, `tag_id`),
              CONSTRAINT `fk.rule_tag.id` FOREIGN KEY (`rule_id`)
                REFERENCES `rule` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.rule_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
