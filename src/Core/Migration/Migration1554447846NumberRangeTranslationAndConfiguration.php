<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1554447846NumberRangeTranslationAndConfiguration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554447846;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        $definitionNumberRangeTypes = [
            'product' => [
                'id' => Uuid::randomHex(),
                'global' => 1,
                'nameDe' => 'Produkt',
            ],
            'order' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Bestellung',
            ],
            'customer' => [
                'id' => Uuid::randomHex(),
                'global' => 0,
                'nameDe' => 'Kunde',
            ],
        ];

        $definitionNumberRanges = [
            'product' => [
                'id' => Uuid::randomHex(),
                'name' => 'Products',
                'nameDe' => 'Produkte',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['product']['id'],
                'pattern' => 'SW{n}',
                'start' => 10000,
            ],
            'order' => [
                'id' => Uuid::randomHex(),
                'name' => 'Orders',
                'nameDe' => 'Bestellungen',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['order']['id'],
                'pattern' => '{n}',
                'start' => 10000,
            ],
            'customer' => [
                'id' => Uuid::randomHex(),
                'name' => 'Customers',
                'nameDe' => 'Kunden',
                'global' => 1,
                'typeId' => $definitionNumberRangeTypes['customer']['id'],
                'pattern' => '{n}',
                'start' => 10000,
            ],
        ];

        $sql = <<<SQL
            DROP TABLE `number_range_state`;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            DROP TABLE `number_range_sales_channel`;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            DROP TABLE `number_range`;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            DROP TABLE `number_range_type`;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range` (
              `id` BINARY(16) NOT NULL,
              `type_id` BINARY(16) NOT NULL,
              `global` TINYINT(1) NOT NULL,
              `pattern` VARCHAR(255) NOT NULL,
              `start` INTEGER(8) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_translation` (
              `number_range_id` BINARY(16) NOT NULL,
              `name` VARCHAR(64) NOT NULL,
              `description` VARCHAR(255) NULL,
              `attributes` JSON NULL,
              `language_id` BINARY(16) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`number_range_id`, `language_id`),
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`)),
              CONSTRAINT `fk.number_range_translation.number_range_id`   FOREIGN KEY (`number_range_id`)
                REFERENCES `number_range` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_state` (
              `id` BINARY(16) NOT NULL,
              `number_range_id` BINARY(16) NOT NULL,
              `last_value` INTEGER(8) NOT NULL,
              PRIMARY KEY (`number_range_id`),
              UNIQUE `uniq.id` (`id`),
              INDEX `idx.number_range_id` (`number_range_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        // No Foreign Key here is intended. It should be possible to handle the state with another Persistence so
        // we can force MySQL to expect a Dependency here
        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_type` (
              `id` BINARY(16) NOT NULL,
              `technical_name` VARCHAR(64),
              `global` TINYINT(1) NOT NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_type_translation` (
              `number_range_type_id` BINARY(16) NOT NULL,
              `language_id` BINARY(16) NOT NULL,
              `type_name` VARCHAR(64) NOT NULL,
              `attributes` JSON NULL,
              `created_at` DATETIME(3) NOT NULL,
              `updated_at` DATETIME(3) NULL,
               PRIMARY KEY (`number_range_type_id`, `language_id`),
              CONSTRAINT `fk.number_range_type_translation.number_range_type_id`   FOREIGN KEY (`number_range_type_id`)
                REFERENCES `number_range_type` (`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_type_translation.language_id`     FOREIGN KEY (`language_id`)
                REFERENCES `language` (`id`)    ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `json.attributes` CHECK (JSON_VALID(`attributes`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($sql);

        $sql = <<<SQL
            CREATE TABLE `number_range_sales_channel` (
              `id` BINARY(16) NOT NULL,
              `number_range_id` BINARY(16) NOT NULL,
              `sales_channel_id` BINARY(16) NULL,
              `number_range_type_id` BINARY(16) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE `uniq.numer_range_id__sales_channel_id` (`number_range_id`, `sales_channel_id`),
              CONSTRAINT `fk.number_range_sales_channel.number_range_id`
                FOREIGN KEY (number_range_id) REFERENCES `number_range` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_sales_channel.sales_channel_id`
                FOREIGN KEY (sales_channel_id) REFERENCES `sales_channel` (id) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `fk.number_range_sales_channel.number_range_type_id`
                FOREIGN KEY (number_range_type_id) REFERENCES `number_range_type` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeQuery($sql);

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($definitionNumberRangeTypes as $typeName => $numberRangeType) {
            $connection->insert(
                'number_range_type',
                [
                    'id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'global' => $numberRangeType['global'],
                    'technical_name' => $typeName,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $typeName,
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_type_translation',
                [
                    'number_range_type_id' => Uuid::fromHexToBytes($numberRangeType['id']),
                    'type_name' => $numberRangeType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        }

        foreach ($definitionNumberRanges as $typeName => $numberRange) {
            $connection->insert(
                'number_range',
                [
                    'id' => Uuid::fromHexToBytes($numberRange['id']),
                    'global' => $numberRange['global'],
                    'type_id' => Uuid::fromHexToBytes($numberRange['typeId']),
                    'pattern' => $numberRange['pattern'],
                    'start' => $numberRange['start'],
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['name'],
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'number_range_translation',
                [
                    'number_range_id' => Uuid::fromHexToBytes($numberRange['id']),
                    'name' => $numberRange['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
        }
    }
}
