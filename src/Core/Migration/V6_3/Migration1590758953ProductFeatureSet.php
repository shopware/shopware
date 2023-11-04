<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\InheritanceUpdaterTrait;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1590758953ProductFeatureSet extends MigrationStep
{
    use InheritanceUpdaterTrait;

    final public const TRANSLATIONS = [
        'en-GB' => [
            'name' => 'Default',
            'description' => 'Default template displaying the product\'s price per scale unit',
        ],
        'de-DE' => [
            'name' => 'Standard',
            'description' => 'Standardtemplate, hebt den Grundpreis des Produkts hervor',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1590758953;
    }

    public function update(Connection $connection): void
    {
        $defaultFeatureSetId = Uuid::randomBytes();

        $this->createTables($connection);
        $this->updateTables($connection);

        $this->insertDefaultFeatureSet($connection, $defaultFeatureSetId);
        $this->insertDefaultFeatureSetTranslations($connection, $defaultFeatureSetId);
        $this->assignDefaultFeatureSet($connection, $defaultFeatureSetId);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function createTables(Connection $connection): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `product_feature_set` (
    `id`         BINARY(16)  NOT NULL,
    `features`   JSON        NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `json.product_feature_set.features` CHECK (JSON_VALID(`features`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_feature_set_translation` (
    `product_feature_set_id` BINARY(16)   NOT NULL,
    `language_id`            BINARY(16)   NOT NULL,
    `name`                   VARCHAR(255) NULL,
    `description`            MEDIUMTEXT   NULL,
    `created_at`             DATETIME(3)  NOT NULL,
    `updated_at`             DATETIME(3)  NULL,
    PRIMARY KEY (`product_feature_set_id`, `language_id`),
    CONSTRAINT `fk.product_feature_set_translation.language_id` FOREIGN KEY (`language_id`)
        REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk.product_feature_set_translation.product_feature_set_id` FOREIGN KEY (`product_feature_set_id`)
        REFERENCES `product_feature_set` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
SQL;

        $connection->executeStatement($sql);
    }

    private function updateTables(Connection $connection): void
    {
        $featureSetColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `product` WHERE `Field` LIKE :column;',
            ['column' => 'product_feature_set_id']
        );
        $featureSetInheritanceColumn = $connection->fetchOne(
            'SHOW COLUMNS FROM `product` WHERE `Field` LIKE :column;',
            ['column' => 'featureSet']
        );

        if ($featureSetColumn === false) {
            $sql = <<<'SQL'
ALTER TABLE `product`
    ADD COLUMN `product_feature_set_id` BINARY(16) NULL AFTER `unit_id`;
ALTER TABLE `product`
    ADD CONSTRAINT `fk.product.feature_set_id` FOREIGN KEY (`product_feature_set_id`)
        REFERENCES `product_feature_set` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
SQL;

            $connection->executeStatement($sql);
        }

        if ($featureSetInheritanceColumn === false) {
            $this->updateInheritance($connection, 'product', 'featureSet');
        }
    }

    private function insertDefaultFeatureSet(Connection $connection, string $featureSetId): void
    {
        $connection->insert(
            ProductFeatureSetDefinition::ENTITY_NAME,
            $this->getDefaultFeatureSet($featureSetId)
        );
    }

    private function insertDefaultFeatureSetTranslations(Connection $connection, string $featureSetId): void
    {
        $languages = $this->fetchLanguageIds($connection, ['en-GB']);
        $languages[] = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languages = array_unique($languages);

        $sql = <<<'SQL'
REPLACE INTO `product_feature_set_translation` (`product_feature_set_id`, `language_id`, `name`, `description`, `created_at`)
VALUES (:product_feature_set_id, :language_id, :name, :description, :created_at);
SQL;

        foreach ($languages as $language) {
            $connection->executeStatement(
                $sql,
                $this->getDefaultFeatureSetTranslation(
                    $featureSetId,
                    $language,
                    self::TRANSLATIONS['en-GB']
                )
            );
        }

        $languages = $this->fetchLanguageIds($connection, ['de-DE']);

        foreach ($languages as $language) {
            $connection->executeStatement(
                $sql,
                $this->getDefaultFeatureSetTranslation(
                    $featureSetId,
                    $language,
                    self::TRANSLATIONS['de-DE']
                )
            );
        }
    }

    private function assignDefaultFeatureSet(Connection $connection, string $featureSetId): void
    {
        $sql = <<<'SQL'
UPDATE `product` SET `product_feature_set_id` = :feature_set_id WHERE `product_feature_set_id` IS NULL;
SQL;

        $connection->executeStatement(
            $sql,
            [
                'feature_set_id' => $featureSetId,
            ]
        );
    }

    /**
     * @return array{id: string, features: string, created_at: string}
     */
    private function getDefaultFeatureSet(string $featureSetId): array
    {
        return [
            'id' => $featureSetId,
            'features' => json_encode([
                [
                    'type' => 'referencePrice',
                    'name' => 'referencePrice',
                    'id' => 'd45b40f6a99c4c2abe66c410369b9d3c',
                    'position' => 1,
                ],
            ], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @param array{name: string, description: string} $translation
     *
     * @return array{product_feature_set_id: string, language_id: string, name: string, description: string, created_at: string}
     */
    private function getDefaultFeatureSetTranslation(string $featureSetId, string $languageId, array $translation): array
    {
        return [
            'product_feature_set_id' => $featureSetId,
            'language_id' => $languageId,
            'name' => $translation['name'],
            'description' => $translation['description'],
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @param list<string> $localeCodes
     *
     * @return array{0?: string}
     */
    private function fetchLanguageIds(Connection $connection, array $localeCodes): array
    {
        $sql = <<<'SQL'
SELECT lang.id
FROM language lang
INNER JOIN locale loc
ON lang.translation_code_id = loc.id AND loc.code IN (:locale_codes);
SQL;

        $languageId = $connection->fetchOne(
            $sql,
            ['locale_codes' => $localeCodes],
            ['locale_codes' => ArrayParameterType::STRING]
        );

        if (\is_array($languageId)) {
            return $languageId;
        }

        return $languageId === false ? [] : [$languageId];
    }
}
