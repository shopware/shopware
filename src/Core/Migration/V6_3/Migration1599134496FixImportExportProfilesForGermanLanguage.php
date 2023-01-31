<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1599134496FixImportExportProfilesForGermanLanguage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599134496;
    }

    public function update(Connection $connection): void
    {
        $germanLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.locale_id = loc.id
            AND loc.code = \'de-DE\';
        ');

        if (!$germanLanguageId) {
            return;
        }

        $englishLanguageId = $connection->fetchOne('
            SELECT lang.id
            FROM language lang
            INNER JOIN locale loc ON lang.locale_id = loc.id
            AND loc.code = \'en-GB\';
        ');

        $sql = <<<'SQL'
            SELECT *
            FROM import_export_profile_translation AS `translation`
            INNER JOIN import_export_profile AS `profile` ON translation.import_export_profile_id = profile.id
            WHERE profile.system_default = 1
            AND language_id = :languageId
SQL;

        $englishData = $connection->fetchAllAssociative($sql, [
            'languageId' => $englishLanguageId,
        ]);
        $germanData = $connection->fetchAllAssociative($sql, [
            'languageId' => $germanLanguageId,
        ]);
        $germanTranslations = $this->getGermanTranslationData();

        $insertSql = <<<'SQL'
            INSERT INTO import_export_profile_translation (`import_export_profile_id`, `language_id`, `label`, `created_at`)
            VALUES (:import_export_profile_id, :language_id, :label, :created_at)
SQL;

        $stmt = $connection->prepare($insertSql);
        foreach ($englishData as $data) {
            if ($this->checkIfInGermanData($data, $germanData)) {
                continue;
            }

            $stmt->executeStatement([
                'import_export_profile_id' => $data['import_export_profile_id'],
                'language_id' => $germanLanguageId,
                'label' => $germanTranslations[$data['name']],
                'created_at' => $data['created_at'],
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    /**
     * @return array<string, string>
     */
    private function getGermanTranslationData(): array
    {
        return [
            'Default category' => 'Standardprofil Kategorie',
            'Default media' => 'Standardprofil Medien',
            'Default variant configuration settings' => 'Standardprofil Variantenkonfiguration',
            'Default newsletter recipient' => 'Standardprofil Newsletter-Empfänger',
            'Default properties' => 'Standardprofil Eigenschaften',
            'Default product' => 'Standardprofil Produkt',
        ];
    }

    /**
     * @param array<string, mixed> $englishRow
     * @param list<array<string, mixed>> $germanData
     */
    private function checkIfInGermanData(array $englishRow, array $germanData): bool
    {
        $germanProfileIds = array_column($germanData, 'import_export_profile_id');

        return \in_array($englishRow['import_export_profile_id'], $germanProfileIds, true);
    }
}
