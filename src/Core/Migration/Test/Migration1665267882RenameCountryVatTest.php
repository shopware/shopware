<?php

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1665267882RenameCountryVat;

class Migration1665267882RenameCountryVatTest extends TestCase
{
    use KernelTestBehaviour;

    public function testChanges() : void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $sql = 'SELECT language.name, country_translation.name FROM country
LEFT JOIN country_translation ON country.id = country_translation.country_id
LEFT JOIN language ON language.id = country_translation.language_id
WHERE country.iso = :iso AND country.iso3 = :iso3';

        $translations = $connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
        $this->assertEquals(
            [
                'English' => 'Holy See',
                'Deutsch' => 'Heiliger Stuhl',
            ],
            $translations
        );

        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($connection);

        $translations = $connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
        $this->assertEquals(
            [
                'English' => 'Vatican City',
                'Deutsch' => 'Staat Vatikanstadt',
            ],
            $translations
        );
    }

}
