<?php

declare(strict_types = 1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\Traits\Translations;

class Migration1665267882RenameCountryVat extends MigrationStep
{
    use ImportTranslationsTrait;

    public function getCreationTimestamp() : int
    {
        return 1665267882;
    }

    public function update(Connection $connection) : void
    {
        $countryId = $connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA', 'iso3' => 'VAT']);
        if ($countryId === false) {
            return;
        }

        $sql = 'SELECT language.name, country_translation.name FROM country_translation
        LEFT JOIN country ON country.id = country_translation.country_id
        LEFT JOIN language ON language.id = country_translation.language_id
        WHERE country.iso = :iso AND country.iso3 = :iso3';

        $currentTranslations = $connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
        if (empty($currentTranslations)) {
            return;
        }

        $replacements = [];
        if (($currentTranslations['English'] ?? null) === 'Holy See') {
            $replacements['English'] = 'Vatican City';
        }
        if (($currentTranslations['Deutsch'] ?? null) === 'Heiliger Stuhl') {
            $replacements['Deutsch'] = 'Staat Vatikanstadt';
        }
        if (empty($replacements)) {
            return;
        }

        $languageIds = $connection->fetchAllKeyValue('SELECT language.name, language.id FROM language');
        if (empty($languageIds)) {
            return;
        }

        foreach ($replacements as $languageName => $newCountryName) {
            $languageId = $languageIds[$languageName] ?? null;
            if ($languageId === null) {
                continue;
            }
            $data     = [
                'name' => $newCountryName,
            ];
            $criteria = [
                'country_id' => $countryId,
                'language_id' => $languageId,
            ];
            $connection->update('country_translation', $data, $criteria);
        }
    }

    public function updateDestructive(Connection $connection) : void
    {
    }

}
