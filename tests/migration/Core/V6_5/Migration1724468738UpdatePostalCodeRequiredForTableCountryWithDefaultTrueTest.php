<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1724468738UpdatePostalCodeRequiredForTableCountryWithDefaultTrue;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1724468738UpdatePostalCodeRequiredForTableCountryWithDefaultTrue
 */
#[Package('core')]
class Migration1724468738UpdatePostalCodeRequiredForTableCountryWithDefaultTrueTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement(
            'UPDATE country SET postal_code_required = 0, updated_at = :updatedAt WHERE iso = "DE"',
            ['updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')]
        );

        $this->connection->executeStatement('UPDATE country SET postal_code_required = 0, updated_at = NULL WHERE iso = "US"');
    }

    public function testMigration(): void
    {
        $countries = $this->connection->fetchAllKeyValue(
            'SELECT iso, postal_code_required FROM country WHERE iso IN (:iso)',
            ['iso' => ['DE', 'US']],
            ['iso' => ArrayParameterType::STRING]
        );

        static::assertSame('0', $countries['DE']);
        static::assertSame('0', $countries['US']);

        $migration = new Migration1724468738UpdatePostalCodeRequiredForTableCountryWithDefaultTrue();
        $migration->update($this->connection);

        $countries = $this->connection->fetchAllKeyValue(
            'SELECT iso, postal_code_required FROM country WHERE iso IN (:iso)',
            ['iso' => ['DE', 'US']],
            ['iso' => ArrayParameterType::STRING]
        );

        static::assertSame('0', $countries['DE']);
        static::assertSame('1', $countries['US']);
    }
}
