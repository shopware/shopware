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
        $results = $connection->fetchFirstColumn('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA', 'iso3' => 'VAT']);

        $countryId = $results[0];

        $translations = new Translations(
            [
                'country_id' => $countryId,
                'name'       => 'Staat Vatikanstadt',
            ], [
                'country_id' => $countryId,
                'name'       => 'Vatican City',
            ]
        );
        $this->importTranslation('country_translation', $translations, $connection);
    }

    public function updateDestructive(Connection $connection) : void
    {
    }

}
