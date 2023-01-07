<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1665267882RenameCountryVat;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1665267882RenameCountryVat
 */
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
        $this->connection->beginTransaction();
        $this->prepare();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testExpectCurrentNamesInDefaultInstallation(): void
    {
        $expected = [
            'en-GB' => 'Holy See',
            'de-DE' => 'Heiliger Stuhl',
        ];
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testMigrationCanBeExecutedMultipleTimes(): void
    {
        $migration = new Migration1665267882RenameCountryVat();
        $expected = [
            'en-GB' => 'Vatican City',
            'de-DE' => 'Staat Vatikanstadt',
        ];
        $migration->update($this->connection);

        static::assertEquals($expected, $this->fetchCurrentTranslations());

        $migration->update($this->connection);
        static::assertEquals($expected, $this->fetchCurrentTranslations());

        $manuelEditedName = 'Vatikanstadt';
        $this->prepareCurrentName('de-DE', $manuelEditedName);
        $expected = [
            'en-GB' => 'Vatican City',
            'de-DE' => $manuelEditedName,
        ];

        $migration->update($this->connection);
        static::assertEquals($expected, $this->fetchCurrentTranslations());

        $migration->update($this->connection);
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testUpdateCountryName(): void
    {
        $this->testExpectCurrentNamesInDefaultInstallation();

        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $expected = [
            'en-GB' => 'Vatican City',
            'de-DE' => 'Staat Vatikanstadt',
        ];
        static::assertEquals($expected, $this->fetchCurrentTranslations());
    }

    public function testUpdateIfCountryNotExist(): void
    {
        $this->connection->executeStatement('DELETE FROM country WHERE iso3 = \'VAT\';');

        $existCountry = (bool) $this->connection->fetchOne('SELECT 1 FROM country WHERE iso3 = \'VAT\'');
        static::assertFalse($existCountry);

        $currentTranslations = $this->fetchCurrentTranslations();
        static::assertEmpty($currentTranslations);

        $migration = new Migration1665267882RenameCountryVat();
        $migration->update($this->connection);

        $currentTranslations = $this->fetchCurrentTranslations();
        static::assertEmpty($currentTranslations);
    }

    public function testSkipUpdateIfManuelEdited(): void
    {
        $this->testExpectCurrentNamesInDefaultInstallation();

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

    private function prepare(): void
    {
        $this->prepareCurrentName('en-GB', self::NAME_EN);
        $this->prepareCurrentName('de-DE', self::NAME_DE);
    }

    /**
     * @return array<mixed>
     */
    private function fetchCurrentTranslations(): array
    {
        $sql = 'SELECT locale.code, country_translation.name FROM country_translation
                LEFT JOIN country ON country.id = country_translation.country_id
                LEFT JOIN language ON language.id = country_translation.language_id
                LEFT JOIN locale ON locale.id = language.locale_id
                WHERE country.iso = :iso AND country.iso3 = :iso3';

        return $this->connection->fetchAllKeyValue($sql, ['iso' => 'VA', 'iso3' => 'VAT']);
    }

    private function prepareCurrentName(string $languageCode, string $countryName): void
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
