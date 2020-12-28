<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

class Migration1607581275AddProductSearchConfiguration extends MigrationStep
{
    use ImportTranslationsTrait;

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1607581275;
    }

    public function update(Connection $connection): void
    {
        $this->createProductSearchConfigTable($connection);
        $this->createProductSearchConfigFieldTable($connection);
        $this->createSearchConfigDefaultData($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createProductSearchConfigTable(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `product_search_config` (
                `id`                    BINARY(16)        NOT NULL,
                `language_id`           BINARY(16)        NOT NULL,
                `and_logic`             TINYINT(1)        NOT NULL DEFAULT 1,
                `min_search_length`     SMALLINT          NOT NULL DEFAULT 2,
                `excluded_terms`        JSON              NULL,
                `created_at`            DATETIME(3)       NOT NULL,
                `updated_at`            DATETIME(3)       NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.product_search_config.excluded_terms` CHECK (JSON_VALID(`excluded_terms`)),
                CONSTRAINT `uniq.product_search_config.language_id` UNIQUE (`language_id`),
                CONSTRAINT `fk.product_search_config.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function createProductSearchConfigFieldTable(Connection $connection): void
    {
        $connection->executeUpdate('
            CREATE TABLE IF NOT EXISTS `product_search_config_field` (
                `id`                            BINARY(16)                                  NOT NULL,
                `product_search_config_id`      BINARY(16)                                  NOT NULL,
                `custom_field_id`               BINARY(16)                                  NULL,
                `field`                         VARCHAR(255)                                NOT NULL,
                `tokenize`                      TINYINT(1)                                  NOT NULL    DEFAULT 0,
                `searchable`                    TINYINT(1)                                  NOT NULL    DEFAULT 0,
                `ranking`                       INT(11)                                     NOT NULL    DEFAULT 0,
                `created_at`                    DATETIME(3)                                 NOT NULL,
                `updated_at`                    DATETIME(3)                                 NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `uniq.search_config_field.field__config_id` UNIQUE (`field`, `product_search_config_id`),
                CONSTRAINT `fk.search_config_field.product_search_config_id` FOREIGN KEY (`product_search_config_id`)
                    REFERENCES `product_search_config` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.search_config_field.custom_field_id` FOREIGN KEY (`custom_field_id`)
                    REFERENCES `custom_field` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function createSearchConfigDefaultData(Connection $connection): void
    {
        $searchConfigEnId = Uuid::randomBytes();
        $searchConfigDeId = Uuid::randomBytes();
        $enLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $deLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);
        $enStopwords = require __DIR__ . '/Fixtures/stopwords/en.php';
        $deStopwords = require __DIR__ . '/Fixtures/stopwords/de.php';

        $translations = new Translations(
            [
                'id' => $searchConfigDeId,
                'and_logic' => 1,
                'min_search_length' => 2,
                'excluded_terms' => json_encode($deStopwords),
            ],
            [
                'id' => $searchConfigEnId,
                'and_logic' => 1,
                'min_search_length' => 2,
                'excluded_terms' => $enLanguageId ? json_encode($enStopwords) : null,
            ]
        );

        $this->importTranslation(ProductSearchConfigDefinition::ENTITY_NAME, $translations, $connection);

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $defaultSearchData = $this->getConfigFieldDefaultData($searchConfigEnId, $createdAt);

        if ($deLanguageId) {
            $defaultSearchData = array_merge(
                $defaultSearchData,
                $this->getConfigFieldDefaultData($searchConfigDeId, $createdAt)
            );
        }

        $queue = new MultiInsertQueryQueue($connection, 250);

        foreach ($defaultSearchData as $searchData) {
            $entityName = $searchData['table'];
            unset($searchData['table']);

            $queue->addInsert($entityName, $searchData);
        }

        $queue->execute();
    }

    private function getConfigFieldDefaultData(string $configId, string $createdAt): array
    {
        $entityName = ProductSearchConfigFieldDefinition::ENTITY_NAME;
        $defaultConfig = [
            'tokenize' => 0,
            'searchable' => 0,
            'ranking' => 0,
        ];

        return [
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'name',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 700,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'description',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'productNumber',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 1000,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'manufacturerNumber',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 500,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'ean',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 500,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'customSearchKeywords',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 800,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'manufacturer.name',
                'tokenize' => 0,
                'searchable' => 0,
                'ranking' => 500,
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'manufacturer.customFields',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'categories.name',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'categories.customFields',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'tags.name',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'metaTitle',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'metaDescription',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'properties.name',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
            [
                'table' => $entityName,
                'id' => Uuid::randomBytes(),
                'product_search_config_id' => $configId,
                'field' => 'variantRestrictions',
                'tokenize' => $defaultConfig['tokenize'],
                'searchable' => $defaultConfig['searchable'],
                'ranking' => $defaultConfig['ranking'],
                'created_at' => $createdAt,
            ],
        ];
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        try {
            return (string) $connection->fetchColumn(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $languageName]
            );
        } catch (DBALException $e) {
            return null;
        }
    }
}
