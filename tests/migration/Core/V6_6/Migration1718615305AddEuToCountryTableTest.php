<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1718615305AddEuToCountryTable;

/**
 * @internal
 */
#[CoversClass(Migration1718615305AddEuToCountryTable::class)]
class Migration1718615305AddEuToCountryTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1718615305, (new Migration1718615305AddEuToCountryTable())->getCreationTimestamp());
    }

    public function testCountryHasNewIsEuColumn(): void
    {
        $this->rollback();
        $this->executeMigration();
        $columns = $this->connection->fetchAllAssociative('SHOW COLUMNS FROM `country`');
        $columnNames = array_column($columns, 'Field');

        $isEuColumnKey = array_search('is_eu', $columnNames, true);

        if ($isEuColumnKey === false) {
            static::fail('Column "is_eu" not found in "country" table');
        }

        static::assertEquals('is_eu', $columns[$isEuColumnKey]['Field']);
        static::assertEquals('tinyint(1)', $columns[$isEuColumnKey]['Type']);
        static::assertEquals('NO', $columns[$isEuColumnKey]['Null']);
        static::assertEquals('0', $columns[$isEuColumnKey]['Default']);
    }

    public function testEuCountriesAreMarkedAsEu(): void
    {
        $this->rollback();
        $this->executeMigration();
        $euCountryIsoCodes = $this->connection->executeQuery(
            'SELECT `iso` FROM `country` WHERE `is_eu` = 1;'
        )->fetchFirstColumn();

        foreach ($this->getEuCountryCodes() as $euCountryCode) {
            static::assertContains($euCountryCode, $euCountryIsoCodes);
        }
    }

    public function executeMigration(): void
    {
        (new Migration1718615305AddEuToCountryTable())->update($this->connection);
    }

    public function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `country` DROP COLUMN `is_eu`');
    }

    /**
     * @return string[]
     */
    private function getEuCountryCodes(): array
    {
        return [
            'AT', // Austria
            'BE', // Belgium
            'BG', // Bulgaria
            'CY', // Cyprus
            'CZ', // Czech Republic
            'DE', // Germany
            'DK', // Denmark
            'EE', // Estonia
            'ES', // Spain
            'FI', // Finland
            'FR', // France
            'GR', // Greece
            'HR', // Croatia
            'HU', // Hungary
            'IE', // Ireland
            'IT', // Italy
            'LT', // Lithuania
            'LU', // Luxembourg
            'LV', // Latvia
            'MT', // Malta
            'NL', // Netherlands
            'PL', // Poland
            'PT', // Portugal
            'RO', // Romania
            'SE', // Sweden
            'SI', // Slovenia
            'SK', // Slovakia
        ];
    }
}
