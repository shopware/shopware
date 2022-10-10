<?php

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1665267882RenameCountryVat;
use Shopware\Tests\Migration\MigrationTestTrait;

class Migration1665267882RenameCountryVatTest extends TestCase
{
    use MigrationTestTrait;

    private const NAME_EN = 'Holy See';
    private const NAME_DE = 'Heiliger Stuhl';
    private Connection $connection;

    public function testCountryVaticanCityExist()
    {
        $countryId = $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA', 'iso3' => 'VAT']);

        $this->assertIsNotBool($countryId, 'Country \'Vatican City\' not found');
    }

    public function testFetchOfNonExistingCountryId()
    {
        $countryId = $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA3', 'iso3' => 'VAT3']);

        $this->assertIsBool($countryId);
        $this->assertEquals($countryId, false);
    }

    public function testPrepare()
    {
        $expected = [
            'English' => 'Holy See',
            'Deutsch' => 'Heiliger Stuhl',
        ];
        $this->assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testUpdateCountryName() : void
    {
        $this->testPrepare();
        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $expected = [
            'English' => 'Vatican City',
            'Deutsch' => 'Staat Vatikanstadt',
        ];
        $this->assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testSkipUpdateIfManuelEdited() : void
    {
        $this->testPrepare();

        $manuelEditedName = 'Vatikanstadt';

        $this->prepareCurrentName('Deutsch', $manuelEditedName);

        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $expected = [
            'English' => 'Vatican City',
            'Deutsch' => $manuelEditedName,
        ];
        $this->assertEquals($expected, $this->fetchCurrentTranslations());
    }

    protected function setUp() : void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    protected function prepare()
    {
        $this->prepareCurrentName('English', self::NAME_EN);
        $this->prepareCurrentName('Deutsch', self::NAME_DE);
    }

    protected function fetchCurrentTranslations()
    {
        $sql = 'SELECT language.name, country_translation.name FROM country_translation
        LEFT JOIN country ON country.id = country_translation.country_id
        LEFT JOIN language ON language.id = country_translation.language_id
        WHERE country.iso = :iso AND country.iso3 = :iso3';

        return $this->connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
    }

    protected function prepareCurrentName(string $languageName, string $countryName)
    {
        $sql = 'UPDATE `country_translation`
LEFT JOIN country ON country.id = country_translation.country_id
LEFT JOIN language ON language.id = country_translation.language_id
SET country_translation.name = :name
WHERE language.name = :language AND country.iso3 = \'VAT\'';

        $this->connection->executeStatement($sql, [
            'language' => $languageName,
            'name' => $countryName,
        ]);
    }

}
