<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Struct\Uuid;

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
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeQuery('
            CREATE TABLE `salutation_translation` (
              `salutation_id` BINARY(16)    NOT NULL,
              `language_id`   BINARY(16)    NOT NULL,
              `name`          VARCHAR(255)  NULL,
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
        $mr = Uuid::uuid4()->getBytes();
        $mrs = Uuid::uuid4()->getBytes();
        $miss = Uuid::uuid4()->getBytes();
        $divers = Uuid::uuid4()->getBytes();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        // Inserts for: Mr.
        $connection->insert('salutation', [
            'id' => $mr,
            'salutation_key' => Defaults::SALUTATION_KEY_MR,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageEn,
            'name' => 'Mr.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mr,
            'language_id' => $languageDe,
            'name' => 'Herr',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Mrs.
        $connection->insert('salutation', [
            'id' => $mrs,
            'salutation_key' => Defaults::SALUTATION_KEY_MRS,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageEn,
            'name' => 'Mrs.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $mrs,
            'language_id' => $languageDe,
            'name' => 'Frau',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Miss
        $connection->insert('salutation', [
            'id' => $miss,
            'salutation_key' => Defaults::SALUTATION_KEY_MISS,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageEn,
            'name' => 'Miss',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $miss,
            'language_id' => $languageDe,
            'name' => 'Fräulein',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);

        // Inserts for: Divers
        $connection->insert('salutation', [
            'id' => $divers,
            'salutation_key' => Defaults::SALUTATION_KEY_DIVERS,
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $divers,
            'language_id' => $languageEn,
            'name' => 'Mx.',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
        $connection->insert('salutation_translation', [
            'salutation_id' => $divers,
            'language_id' => $languageDe,
            'name' => 'Herr/Frau',
            'created_at' => date(Defaults::DATE_FORMAT),
        ]);
    }
}
