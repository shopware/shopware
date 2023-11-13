<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
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
#[Package('system-settings')]
class Migration1677470540AddProvincesForCanada extends MigrationStep
{
    use ImportTranslationsTrait;

    public const CANADA_STATES = [
        // 10 Provinces
        [
            'nameEN' => 'Ontario',
            'nameDE' => 'Ontario',
            'shortCode' => 'CA-ON',
        ],
        [
            'nameEN' => 'Quebec',
            'nameDE' => 'Québec',
            'shortCode' => 'CA-QC',
        ],
        [
            'nameEN' => 'Nova Scotia',
            'nameDE' => 'Nova Scotia',
            'shortCode' => 'CA-NS',
        ],
        [
            'nameEN' => 'New Brunswick',
            'nameDE' => 'New Brunswick',
            'shortCode' => 'CA-NB',
        ],
        [
            'nameEN' => 'Manitoba',
            'nameDE' => 'Manitoba',
            'shortCode' => 'CA-MB',
        ],
        [
            'nameEN' => 'British Columbia',
            'nameDE' => 'British Columbia',
            'shortCode' => 'CA-BC',
        ],
        [
            'nameEN' => 'Prince Edward Island',
            'nameDE' => 'Prince Edward Island',
            'shortCode' => 'CA-PE',
        ],
        [
            'nameEN' => 'Saskatchewan',
            'nameDE' => 'Saskatchewan',
            'shortCode' => 'CA-SK',
        ],
        [
            'nameEN' => 'Alberta',
            'nameDE' => 'Alberta',
            'shortCode' => 'CA-AB',
        ],
        [
            'nameEN' => 'Newfoundland and Labrador',
            'nameDE' => 'Neufundland und Labrador',
            'shortCode' => 'CA-NL',
        ],
        // 3 Territories
        [
            'nameEN' => 'Northwest Territories',
            'nameDE' => 'Nordwest-Territorien',
            'shortCode' => 'CA-NT',
        ],
        [
            'nameEN' => 'Yukon',
            'nameDE' => 'Yukon',
            'shortCode' => 'CA-YT',
        ],
        [
            'nameEN' => 'Nunavut',
            'nameDE' => 'Nunavut',
            'shortCode' => 'CA-NU',
        ],
    ];

    public function getCreationTimestamp(): int
    {
        return 1677470540;
    }

    public function update(Connection $connection): void
    {
        $countryId = $connection->fetchOne('SELECT id from country WHERE iso = \'CA\' AND iso3 = \'CAN\'');

        if (!$countryId) {
            return;
        }

        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $queue = new MultiInsertQueryQueue($connection, \count(self::CANADA_STATES), false, true);
        $countryStateTranslations = [];

        $shortCodes = array_map(fn ($state) => $state['shortCode'], self::CANADA_STATES);

        $existStates = $connection->fetchFirstColumn(
            'SELECT short_code FROM country_state WHERE short_code IN (:shortCodes)',
            ['shortCodes' => $shortCodes],
            ['shortCodes' => ArrayParameterType::STRING]
        );

        foreach (self::CANADA_STATES as $state) {
            // skip if exist state
            if (\in_array($state['shortCode'], $existStates, true)) {
                continue;
            }

            $countryStateId = Uuid::randomBytes();

            $countryStateData = [
                'id' => $countryStateId,
                'country_id' => $countryId,
                'short_code' => $state['shortCode'],
                'position' => 1,
                'active' => 1,
                'created_at' => $createdAt,
            ];

            $queue->addInsert('country_state', $countryStateData);

            $countryStateTranslations[] = new Translations([
                'country_state_id' => $countryStateId,
                'name' => $state['nameDE'],
            ], [
                'country_state_id' => $countryStateId,
                'name' => $state['nameEN'],
            ]);
        }

        $queue->execute();

        foreach ($countryStateTranslations as $translations) {
            $this->importTranslation('country_state_translation', $translations, $connection);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
