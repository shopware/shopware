<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1582011195FixCountryStateGermanTranslation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1582011195;
    }

    public function update(Connection $connection): void
    {
        $default = [
            'DE-BW' => 'Baden-Württemberg',
            'DE-BY' => 'Bavaria',
            'DE-BE' => 'Berlin',
            'DE-BB' => 'Brandenburg',
            'DE-HB' => 'Bremen',
            'DE-HH' => 'Hamburg',
            'DE-HE' => 'Hesse',
            'DE-NI' => 'Lower Saxony',
            'DE-MV' => 'Mecklenburg-Western Pomerania',
            'DE-NW' => 'North Rhine-Westphalia',
            'DE-RP' => 'Rhineland-Palatinate',
            'DE-SL' => 'Saarland',
            'DE-SN' => 'Saxony',
            'DE-ST' => 'Saxony-Anhalt',
            'DE-SH' => 'Schleswig-Holstein',
            'DE-TH' => 'Thuringia',
        ];

        $germanTranslations = [
            'DE-BW' => 'Baden-Württemberg',
            'DE-BY' => 'Bayern',
            'DE-BE' => 'Berlin',
            'DE-BB' => 'Brandenburg',
            'DE-HB' => 'Bremen',
            'DE-HH' => 'Hamburg',
            'DE-HE' => 'Hessen',
            'DE-NI' => 'Niedersachsen',
            'DE-MV' => 'Mecklenburg-Vorpommern',
            'DE-NW' => 'Nordrhein-Westfalen',
            'DE-RP' => 'Rheinland-Pfalz',
            'DE-SL' => 'Saarland',
            'DE-SN' => 'Sachsen',
            'DE-ST' => 'Sachsen-Anhalt',
            'DE-SH' => 'Schleswig-Holstein',
            'DE-TH' => 'Thüringen',
        ];

        $germanLanguageId = $connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :germanLocale')
            ->setParameter('germanLocale', 'de-DE')
            ->executeQuery()
            ->fetchOne();

        if (!$germanLanguageId) {
            return;
        }

        $translations = $connection->createQueryBuilder()
            ->select('state.short_code, state.id, state_translation.name')
            ->from('country_state', 'state')
            ->innerJoin(
                'state',
                'country_state_translation',
                'state_translation',
                'state.id = state_translation.country_state_id AND state_translation.language_id = :germanLanguageId'
            )->where('state.short_code IN (:shortCodes)')
            ->setParameter('germanLanguageId', $germanLanguageId)
            ->setParameter('shortCodes', array_keys($default), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($translations as $translation) {
            $shortCode = $translation['short_code'];

            if ($translation['name'] !== $default[$shortCode]) {
                continue;
            }

            $connection->update(
                'country_state_translation',
                ['name' => $germanTranslations[$shortCode]],
                [
                    'country_state_id' => $translation['id'],
                    'language_id' => $germanLanguageId,
                ]
            );
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
