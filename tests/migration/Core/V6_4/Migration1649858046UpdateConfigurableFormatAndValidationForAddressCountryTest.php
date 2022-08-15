<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_4\Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry
 */
class Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountryTest extends TestCase
{
    private Connection $connection;

    /**
     * @var Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry
     */
    private $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry();
    }

    public function testMigration(): void
    {
        $this->prepare();
        $this->migration->update($this->connection);

        $advancedPostalCodePatternColumnExists = $this->hasColumn('country', 'advanced_postal_code_pattern');
        static::assertTrue($advancedPostalCodePatternColumnExists);

        $checkAdvancedPostalCodePatternColumnExists = $this->hasColumn('country', 'check_advanced_postal_code_pattern');
        static::assertTrue($checkAdvancedPostalCodePatternColumnExists);

        $checkPostalCodePatternColumnExists = $this->hasColumn('country', 'check_postal_code_pattern');
        static::assertTrue($checkPostalCodePatternColumnExists);

        $postalCodeRequiredColumnExists = $this->hasColumn('country', 'postal_code_required');
        static::assertTrue($postalCodeRequiredColumnExists);
    }

    private function prepare(): void
    {
        $advancedPostalCodePatternColumnExists = $this->hasColumn('country', 'advanced_postal_code_pattern');

        if ($advancedPostalCodePatternColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country` DROP COLUMN `advanced_postal_code_pattern`');
        }

        $checkAdvancedPostalCodePatternColumnExists = $this->hasColumn('country', 'check_advanced_postal_code_pattern');

        if ($checkAdvancedPostalCodePatternColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country` DROP COLUMN `check_advanced_postal_code_pattern`');
        }

        $checkPostalCodePatternColumnExists = $this->hasColumn('country', 'check_postal_code_pattern');

        if ($checkPostalCodePatternColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country` DROP COLUMN `check_postal_code_pattern`');
        }

        $postalCodeRequiredColumnExists = $this->hasColumn('country', 'postal_code_required');

        if ($postalCodeRequiredColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country` DROP COLUMN `postal_code_required`');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}
