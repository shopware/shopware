<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1551954639Salutation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1551954639;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('
            CREATE TABLE `salutation` (
              `id`             BINARY(16)    NOT NULL,
              `salutation_key` VARCHAR(255)  NOT NULL,
              `created_at`     DATETIME(3)   NOT NULL,
              `updated_at`     DATETIME(3)   NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq.salutation_key` (`salutation_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `salutation_translation` (
              `salutation_id` BINARY(16)    NOT NULL,
              `language_id`   BINARY(16)    NOT NULL,
              `display_name`  VARCHAR(255)  NULL,
              `letter_name`   VARCHAR(255)  NULL,
              `created_at`    DATETIME(3)   NOT NULL,
              `updated_at`    DATETIME(3)   NULL,
              PRIMARY KEY (`salutation_id`, `language_id`),
              CONSTRAINT `fk.salutation_translation.salutation_id`   FOREIGN KEY (`salutation_id`)
                REFERENCES `salutation` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.salutation_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $this->createSalutation($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createSalutation(Connection $connection): void
    {
        $mr = Uuid::randomBytes();
        $mrs = Uuid::randomBytes();
        $miss = Uuid::randomBytes();
        $diverse = Uuid::randomBytes();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // Inserts for: Mr.
        $connection->insert('salutation', [
            'id' => $mr,
            'salutation_key' => 'mr',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageEn,
            'display_name' => 'Mr.',
            'letter_name' => 'Dear Mr.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageDe,
            'display_name' => 'Herr',
            'letter_name' => 'Sehr geehrter Herr',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Mrs.
        $connection->insert('salutation', [
            'id' => $mrs,
            'salutation_key' => 'mrs',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageEn,
            'display_name' => 'Mrs.',
            'letter_name' => 'Dear Mrs.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageDe,
            'display_name' => 'Frau',
            'letter_name' => 'Sehr geehrte Frau',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Miss
        $connection->insert('salutation', [
            'id' => $miss,
            'salutation_key' => 'miss',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageEn,
            'display_name' => 'Miss',
            'letter_name' => 'Dear Miss',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageDe,
            'display_name' => 'Fräulein',
            'letter_name' => 'Sehr geehrtes Fräulein',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Diverse
        $connection->insert('salutation', [
            'id' => $diverse,
            'salutation_key' => 'diverse',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageEn,
            'display_name' => 'Mx.',
            'letter_name' => 'Dear Mx.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $diverse,
            'language_id' => $languageDe,
            'display_name' => 'Divers',
            'letter_name' => ' ',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }
}
