<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeDefinition;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRangeRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\ZipCodeRuleTypeFilter;

/**
 * @internal
 */
#[Package('core')]
class Migration1572193798TaxRule extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1572193798;
    }

    public function update(Connection $connection): void
    {
        $this->createTables($connection);
        $this->addTaxRuleTypes($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    public function createTables(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE `tax_rule_type`
            (
                `id` BINARY(16) NOT NULL,
                `technical_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                `position` INT(11) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.technical_name` (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeStatement('
            CREATE TABLE `tax_rule_type_translation`
            (
                `tax_rule_type_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `type_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`tax_rule_type_id`, `language_id`),
                CONSTRAINT `fk.tax_rule_type_translation.tax_rule_type_id`
                    FOREIGN KEY (`tax_rule_type_id`) REFERENCES `tax_rule_type` (`id`),
                CONSTRAINT `fk.tax_rule_type_translation.language_id`
                    FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
        $connection->executeStatement('
            CREATE TABLE `tax_rule`
            (
                `id` BINARY(16) NOT NULL,
                `tax_id` BINARY(16) NOT NULL,
                `tax_rule_type_id` BINARY(16) NOT NULL,
                `country_id` BINARY(16) NOT NULL,
                `tax_rate` DOUBLE(10,2) NOT NULL,
                `data` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `json.tax_rule.data` CHECK(JSON_VALID(`data`)),
                CONSTRAINT `fk.tax_rule.tax_id`
                    FOREIGN KEY (`tax_id`) REFERENCES `tax` (`id`),
                CONSTRAINT `fk.tax_rule.tax_area_rule_type_id`
                    FOREIGN KEY (`tax_rule_type_id`) REFERENCES `tax_rule_type` (`id`),
                CONSTRAINT `fk.tax_rule.country_id`
                    FOREIGN KEY (`country_id`) REFERENCES `country` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function addTaxRuleTypes(Connection $connection): void
    {
        $languageIdEn = $this->getLocaleId($connection, 'en-GB');
        $languageIdDe = $this->getLocaleId($connection, 'de-DE');
        $languageSystem = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $dataDe = [
            ZipCodeRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Postleitzahl',
            ],
            ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Postleitzahl Bereich',
            ],
            IndividualStatesRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Individuelle Bundesländer',
            ],
            EntireCountryRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Gesamte Land',
            ],
        ];

        $dataEn = [
            ZipCodeRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Zip Code',
            ],
            ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Zip Code Range',
            ],
            IndividualStatesRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Individual States',
            ],
            EntireCountryRuleTypeFilter::TECHNICAL_NAME => [
                'type_name' => 'Entire Country',
            ],
        ];

        foreach (
            [
                ZipCodeRuleTypeFilter::TECHNICAL_NAME,
                ZipCodeRangeRuleTypeFilter::TECHNICAL_NAME,
                IndividualStatesRuleTypeFilter::TECHNICAL_NAME,
                EntireCountryRuleTypeFilter::TECHNICAL_NAME,
            ] as $position => $technicalName
        ) {
            $typeId = Uuid::randomBytes();
            $typeData = [
                'id' => $typeId,
                'technical_name' => $technicalName,
                'position' => $position,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];
            $connection->insert(TaxRuleTypeDefinition::ENTITY_NAME, $typeData);

            if (!\in_array($languageSystem, [$languageIdDe, $languageIdEn], true)) {
                $this->insertTranslation($connection, $dataEn[$technicalName], $typeId, $languageSystem);
            }

            $this->insertTranslation($connection, $dataEn[$technicalName], $typeId, $languageIdEn);
            $this->insertTranslation($connection, $dataDe[$technicalName], $typeId, $languageIdDe);
        }
    }

    private function getLocaleId(Connection $connection, string $code): ?string
    {
        $result = $connection->fetchOne(
            '
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = :code',
            [
                'code' => $code,
            ]
        );

        if ($result === false) {
            return null;
        }

        return (string) $result;
    }

    /**
     * @param array<string, string> $data
     */
    private function insertTranslation(Connection $connection, array $data, string $typeId, ?string $languageId): void
    {
        if ($languageId === null) {
            return;
        }

        $data = array_merge($data, [
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'language_id' => $languageId,
            'tax_rule_type_id' => $typeId,
        ]);

        $connection->insert('tax_rule_type_translation', $data);
    }
}
