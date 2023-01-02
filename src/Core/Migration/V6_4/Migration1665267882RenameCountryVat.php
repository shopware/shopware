<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1665267882RenameCountryVat extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp(): int
    {
        return 1665267882;
    }

    public function update(Connection $connection): void
    {
        $countryId = $connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA', 'iso3' => 'VAT']);
        if ($countryId === false) {
            return;
        }

        $sql = 'SELECT locale.code, country_translation.name FROM country_translation
                LEFT JOIN country ON country.id = country_translation.country_id
                LEFT JOIN language ON language.id = country_translation.language_id
                LEFT JOIN locale ON locale.id = language.locale_id
                WHERE country.iso = :iso AND country.iso3 = :iso3';

        $currentTranslations = $connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
        if (empty($currentTranslations)) {
            return;
        }

        $replacements = [];
        if (($currentTranslations['en-GB'] ?? null) === 'Holy See') {
            $replacements['en-GB'] = 'Vatican City';
        }
        if (($currentTranslations['de-DE'] ?? null) === 'Heiliger Stuhl') {
            $replacements['de-DE'] = 'Staat Vatikanstadt';
        }
        if (empty($replacements)) {
            return;
        }

        $sql = 'SELECT locale.code, language.id FROM language
                LEFT JOIN locale ON locale.id = language.locale_id';
        $languageIds = $connection->fetchAllKeyValue($sql);
        if (empty($languageIds)) {
            return;
        }

        foreach ($replacements as $languageCode => $newCountryName) {
            $languageId = $languageIds[$languageCode] ?? null;
            if ($languageId === null) {
                continue;
            }
            $data = [
                'name' => $newCountryName,
            ];
            $criteria = [
                'country_id' => $countryId,
                'language_id' => $languageId,
            ];
            $connection->update('country_translation', $data, $criteria);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
