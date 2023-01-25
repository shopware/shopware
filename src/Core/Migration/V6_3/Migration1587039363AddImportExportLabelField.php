<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1587039363AddImportExportLabelField extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1587039363;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `import_export_profile` MODIFY `name` VARCHAR(255) NULL;');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `import_export_profile_translation` (
                `import_export_profile_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `label` VARCHAR(255) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`import_export_profile_id`, `language_id`),
                CONSTRAINT `fk.import_export_profile_translation.import_export_profile_id` FOREIGN KEY (`import_export_profile_id`)
                    REFERENCES `import_export_profile` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.import_export_profile_translation.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $translationsPresent = $connection->fetchOne('SELECT 1 FROM `import_export_profile_translation` LIMIT 1;');

        if ($translationsPresent !== false) {
            return;
        }

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $englishLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = \'en-GB\';
        ');
        $germanLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.translation_code_id = loc.id
            AND loc.code = \'de-DE\';
        ');

        $insertNamesAsLabelsStatement = $connection->prepare('
            INSERT INTO `import_export_profile_translation` (`import_export_profile_id`, `language_id`, `label`, `created_at`)
            SELECT `id`, :languageId, `name`, NOW()
            FROM `import_export_profile`;
        ');

        $insertGermanLabelsStatement = $connection->prepare('
            CREATE TEMPORARY TABLE `temp_import_export_profile_translation` (id int(11) NOT NULL, PRIMARY KEY (id));
            SELECT `id`, `name` AS `label` FROM import_export_profile;
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Kategorie\' WHERE `label` = \'Default category\';
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Medien\' WHERE `label` = \'Default media\';
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Variantenkonfiguration\' WHERE `label` = \'Default variant configuration settings\';
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Newsletter-Empfänger\' WHERE `label` = \'Default newsletter recipient\';
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Eigenschaften\' WHERE `label` = \'Default properties\';
            UPDATE `temp_import_export_profile_translation` SET `label` = \'Standardprofil Produkt\' WHERE `label` = \'Default product\';

            INSERT INTO `import_export_profile_translation` (`import_export_profile_id`, `language_id`, `label`, `created_at`)
            SELECT `id`, :languageId, `label`, NOW()
            FROM `temp_import_export_profile_translation`;
        ');

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $insertNamesAsLabelsStatement->executeStatement([
                'languageId' => $defaultLanguageId,
            ]);
        }

        if ($englishLanguageId) {
            $insertNamesAsLabelsStatement->executeStatement([
                'languageId' => $englishLanguageId,
            ]);
        }

        if ($germanLanguageId) {
            $insertGermanLabelsStatement->executeStatement([
                'languageId' => $germanLanguageId,
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
