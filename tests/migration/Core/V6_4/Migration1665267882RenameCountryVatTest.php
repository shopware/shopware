<?php

declare(strict_types=1);

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->prepare();
    }

    public function testCountryVaticanCityExist(): void
    {
        $countryId = $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA', 'iso3' => 'VAT']);

        static::assertIsNotBool($countryId, 'Country \'Vatican City\' not found');
    }

    public function testFetchOfNonExistingCountryId(): void
    {
        $countryId = $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso AND iso3 = :iso3', ['iso' => 'VA3', 'iso3' => 'VAT3']);

        static::assertIsBool($countryId);
        static::assertEquals($countryId, false);
    }

    public function testPrepare(): void
    {
        $expected = [
            'en-GB' => 'Holy See',
            'de-DE' => 'Heiliger Stuhl',
        ];
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testUpdateCountryName(): void
    {
        $this->testPrepare();
        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $expected = [
            'en-GB' => 'Vatican City',
            'de-DE' => 'Staat Vatikanstadt',
        ];
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testSkipUpdateIfManuelEdited(): void
    {
        $this->testPrepare();

        $manuelEditedName = 'Vatikanstadt';

        $this->prepareCurrentName('de-DE', $manuelEditedName);

        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $expected = [
            'en-GB' => 'Vatican City',
            'de-DE' => $manuelEditedName,
        ];
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    protected function prepare(): void
    {
        $this->prepareCurrentName('en-GB', self::NAME_EN);
        $this->prepareCurrentName('de-DE', self::NAME_DE);
    }

    protected function fetchCurrentTranslations(): array
    {
        $sql = 'SELECT locale.code, country_translation.name FROM country_translation
                LEFT JOIN country ON country.id = country_translation.country_id
                LEFT JOIN language ON language.id = country_translation.language_id
                LEFT JOIN locale ON locale.id = language.locale_id
                WHERE country.iso = :iso AND country.iso3 = :iso3';

        return $this->connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
    }

    protected function prepareCurrentName(string $languageCode, string $countryName): void
    {
        $sql = 'UPDATE `country_translation`
                LEFT JOIN country ON country.id = country_translation.country_id
                LEFT JOIN language ON language.id = country_translation.language_id
                LEFT JOIN locale ON locale.id = language.locale_id
                SET country_translation.name = :name
                WHERE locale.code = :languageCode AND country.iso3 = \'VAT\'';

        $this->connection->executeStatement($sql, [
            'languageCode' => $languageCode,
            'name' => $countryName,
        ]);
    }
}
