<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

/**
 * @internal
 */
#[Package('core')]
class Migration1607581276AddProductSearchConfigurationDefaults extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1607581276;
    }

    public function update(Connection $connection): void
    {
        $this->createSearchConfigDefaultData($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createSearchConfigDefaultData(Connection $connection): void
    {
        $enLanguageId = $this->fetchLanguageIdByName('en-GB', $connection);
        $deLanguageId = $this->fetchLanguageIdByName('de-DE', $connection);

        $searchConfigEnId = $connection->fetchOne('SELECT id FROM product_search_config WHERE language_id = :language_id', ['language_id' => $enLanguageId])
            ?: Uuid::randomBytes();

        $searchConfigDeId = $connection->fetchOne('SELECT id FROM product_search_config WHERE language_id = :language_id', ['language_id' => $deLanguageId])
            ?: Uuid::randomBytes();

        $enStopwords = require __DIR__ . '/../Fixtures/stopwords/en.php';
        $deStopwords = require __DIR__ . '/../Fixtures/stopwords/de.php';

        $translations = new Translations(
            [
                'id' => $searchConfigDeId,
                'and_logic' => 1,
                'min_search_length' => 2,
                'excluded_terms' => json_encode($deStopwords, \JSON_THROW_ON_ERROR),
            ],
            [
                'id' => $searchConfigEnId,
                'and_logic' => 1,
                'min_search_length' => 2,
                'excluded_terms' => $enLanguageId ? json_encode($enStopwords, \JSON_THROW_ON_ERROR) : null,
            ]
        );

        $writeResult = $this->importTranslation(ProductSearchConfigDefinition::ENTITY_NAME, $translations, $connection);

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $defaultSearchData = [];
        if ($writeResult->hasWrittenEnglishTranslations()) {
            $defaultSearchData = $this->getConfigFieldDefaultData($searchConfigEnId, $createdAt);
        }

        if ($writeResult->hasWrittenGermanTranslations()) {
            $defaultSearchData = [...$defaultSearchData, ...$this->getConfigFieldDefaultData($searchConfigDeId, $createdAt)];
        }

        $queue = new MultiInsertQueryQueue($connection, 250);

        foreach ($defaultSearchData as $searchData) {
            $entityName = $searchData['table'];
            unset($searchData['table']);

            $queue->addInsert($entityName, $searchData);
        }

        $queue->execute();
    }

    /**
     * @return list<array{table: string, id: string, product_search_config_id: string, field: string, tokenize: int, searchable: int, ranking: int, created_at: string}>
     */
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

    private function fetchLanguageIdByName(string $isoCode, Connection $connection): ?string
    {
        $languageId = $connection->fetchOne(
            'SELECT `language`.id FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` = :code',
            ['code' => $isoCode]
        );

        return $languageId === false ? null : $languageId;
    }
}
