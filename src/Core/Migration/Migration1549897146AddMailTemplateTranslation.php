<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1549897146AddMailTemplateTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1549897146;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `mail_template_translation` (
              `mail_template_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `sender_name` varchar(255) DEFAULT NULL,
              `subject` varchar(255) DEFAULT NULL,
              `description` longtext DEFAULT NULL,
              `content_html` longtext DEFAULT NULL,
              `content_plain` longtext DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) DEFAULT NULL,
              PRIMARY KEY (`mail_template_id`, `language_id`),
              CONSTRAINT `fk.mail_template_translation.mail_template_id` FOREIGN KEY (`mail_template_id`) REFERENCES `mail_template` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_translation.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
