<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1555421378NewsletterReceiverTags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555421378;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `newsletter_receiver_tag` (
              `newsletter_receiver_id` BINARY(16) NOT NULL,
              `tag_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`newsletter_receiver_id`, `tag_id`),
              CONSTRAINT `fk.newsletter_receiver_tag.id` FOREIGN KEY (`newsletter_receiver_id`)
                REFERENCES `newsletter_receiver` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.newsletter_receiver_tag.tag_id` FOREIGN KEY (`tag_id`)
                REFERENCES `tag` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
