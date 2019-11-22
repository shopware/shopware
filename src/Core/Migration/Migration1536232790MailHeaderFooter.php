<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1536232790MailHeaderFooter extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232790;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `mail_header_footer` (
              `id`              BINARY(16)          NOT NULL,
              `system_default`  TINYINT(1) unsigned NOT NULL DEFAULT \'0\',
              `created_at`      DATETIME(3)         NOT NULL,
              `updated_at`      DATETIME(3)         NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `mail_header_footer_translation` (
              `mail_header_footer_id`   BINARY(16)      NOT NULL,
              `language_id`             BINARY(16)      NOT NULL,
              `name`                    VARCHAR(255)    NULL,
              `description`             LONGTEXT        NULL,
              `header_html`             LONGTEXT        NULL,
              `header_plain`            LONGTEXT        NULL,
              `footer_html`             LONGTEXT        NULL,
              `footer_plain`            LONGTEXT        NULL,
              `created_at`              DATETIME(3)     NOT NULL,
              `updated_at`              DATETIME(3)     NULL,
              PRIMARY KEY (`mail_header_footer_id`, `language_id`),
              CONSTRAINT `fk.mail_header_footer_translation.mail_header_footer_id` FOREIGN KEY (`mail_header_footer_id`) 
                REFERENCES `mail_header_footer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_header_footer_translation.language_id` FOREIGN KEY (`language_id`) 
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
