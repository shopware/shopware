<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1550220273AddMailHeaderFooterTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1550220273;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `mail_header_footer_translation` (
              `mail_header_footer_id` binary(16) NOT NULL,
              `language_id` binary(16) NOT NULL,
              `name` varchar(255) DEFAULT NULL,
              `description` longtext DEFAULT NULL,
              `header_html` longtext DEFAULT NULL,
              `header_plain` longtext DEFAULT NULL,
              `footer_html` longtext DEFAULT NULL,
              `footer_plain` longtext DEFAULT NULL,
              `created_at` datetime(3) NOT NULL,
              `updated_at` datetime(3) DEFAULT NULL,
              PRIMARY KEY (`mail_header_footer_id`, `language_id`),
              CONSTRAINT `fk.mail_header_footer_translation.mail_header_footer_id` 
                  FOREIGN KEY (`mail_header_footer_id`) 
                  REFERENCES `mail_header_footer` (`id`) 
                  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_header_footer_translation.language_id` 
                  FOREIGN KEY (`language_id`) 
                  REFERENCES `language` (`id`) 
                  ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
