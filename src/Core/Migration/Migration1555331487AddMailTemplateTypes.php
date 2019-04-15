<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1555331487AddMailTemplateTypes extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1555331487;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
            CREATE TABLE `mail_template_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $connection->executeUpdate($sql);

        $sql = <<<SQL
            CREATE TABLE `mail_template_type_translation` (
              `mail_template_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`mail_template_type_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.mail_template_type_translation.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.mail_template_type_translation.language_id` FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        $connection->executeUpdate($sql);

        $sql = <<<SQL
            ALTER TABLE  `mail_template`
                ADD COLUMN `mail_template_type_id` binary(16) NULL;
SQL;
        $connection->executeQuery($sql);

        $definitionMailTypes = [
            NewsletterSubscriptionServiceInterface::MAIL_TYPE_OPT_IN => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter Double Opt-In',
                'nameDe' => 'Newsletter Double Opt-In',
            ],
            NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER => [
                'id' => Uuid::randomHex(),
                'name' => 'Newsletter Register',
                'nameDe' => 'Newsetter Registrierung',
            ],
        ];

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($definitionMailTypes as $typeName => $mailType) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => Uuid::fromHexToBytes($mailType['id']),
                    'technical_name' => $typeName,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['name'],
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );

            $sql = <<<SQL
            UPDATE `mail_template`
                SET `mail_template_type_id` = UNHEX(:type_id)
            WHERE `mail_type` = :technical_name;
SQL;
            $connection->executeQuery($sql, ['type_id' => $mailType['id'], 'technical_name' => $typeName]);
        }

        $sql = <<<SQL
            ALTER TABLE  `mail_template`
                DROP COLUMN `mail_type`,
                ADD CONSTRAINT `fk.mail_template_type.mail_template_type_id` FOREIGN KEY (`mail_template_type_id`)
                REFERENCES `mail_template_type` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SQL;
        $connection->executeQuery($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
