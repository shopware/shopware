<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1599570560FixSlovakiaDisplayedAsSlovenia extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1599570560;
    }

    public function update(Connection $connection): void
    {
        $languageEN = null;
        $languageDE = null;
        $countryIdSlovakia = null;

        try {
            $languageEN = $connection->fetchOne('SELECT language.id FROM language INNER JOIN locale
            ON language.translation_code_id = locale.id AND locale.code = \'en-GB\'');
        } catch (\Exception) {
            //English language not found, no need to update the snippet
        }

        try {
            $languageDE = $connection->fetchOne('SELECT language.id FROM language INNER JOIN locale
            ON language.translation_code_id = locale.id AND locale.code = \'de-DE\'');
        } catch (\Exception) {
            //German language not found, no need to update the snippet
        }

        try {
            $countryIdSlovakia = $connection->fetchOne('SELECT id from country WHERE iso3 = \'SVK\'');
        } catch (\Exception) {
            //country got deleted, no need to update
        }

        if ($countryIdSlovakia) {
            $currentDateTime = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            if ($languageEN) {
                try {
                    $connection->update(
                        'country_translation',
                        ['name' => 'Slovakia', 'updated_at' => $currentDateTime],
                        [
                            'country_id' => $countryIdSlovakia,
                            'language_id' => $languageEN,
                            'name' => 'Slovenia',
                        ]
                    );
                } catch (\Exception) {
                }
            }

            if ($languageDE) {
                try {
                    $connection->update(
                        'country_translation',
                        ['name' => 'Slowakei', 'updated_at' => $currentDateTime],
                        [
                            'country_id' => $countryIdSlovakia,
                            'language_id' => $languageDE,
                            'name' => 'Slowenien',
                        ]
                    );
                } catch (\Exception) {
                }
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
