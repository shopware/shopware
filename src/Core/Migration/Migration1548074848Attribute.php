<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1548074848Attribute extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1548074848;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `attribute` (
              `id` binary(16) NOT NULL PRIMARY KEY,
              `name` varchar(255) NOT NULL,
              `type` varchar(255) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              CONSTRAINT `uniq.attribute.name` UNIQUE  (`name`)
            )
        ');

        $connection->exec('
            CREATE TABLE `attribute_translation` (
              `attribute_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `label` varchar(255) NOT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3),
              PRIMARY KEY (`attribute_id`, `language_id`),
              CONSTRAINT `fk.attribute_translation.attribute_id`
                FOREIGN KEY (`attribute_id`) REFERENCES `attribute` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.attribute_translation.language_id`
                FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
