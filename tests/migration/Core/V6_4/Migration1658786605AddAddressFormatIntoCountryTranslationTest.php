<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1658786605AddAddressFormatIntoCountryTranslation;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1658786605AddAddressFormatIntoCountryTranslation
 */
class Migration1658786605AddAddressFormatIntoCountryTranslationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationColumn(): void
    {
        $this->removeColumn();
        static::assertFalse($this->hasColumn('country_translation', 'address_format'));

        $migration = new Migration1658786605AddAddressFormatIntoCountryTranslation();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasColumn('country_translation', 'address_format'));
    }

    private function removeColumn(): void
    {
        if ($this->hasColumn('country_translation', 'address_format')) {
            $this->connection->executeStatement('ALTER TABLE `country_translation` DROP COLUMN `address_format`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \in_array($columnName, array_column($this->connection->fetchAllAssociative(\sprintf('SHOW COLUMNS FROM `%s`', $table)), 'Field'), true);
    }
}
