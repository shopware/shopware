<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry;

/**
 * @internal
 */
class Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry
     */
    private $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1649858046UpdateConfigurableFormatAndValidationForAddressCountry();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        $useDefaultAddressFormatColumnExists = $this->hasColumn('country', 'use_default_address_format');
        static::assertTrue($useDefaultAddressFormatColumnExists);

        $advancedPostalCodePatternColumnExists = $this->hasColumn('country', 'advanced_postal_code_pattern');
        static::assertTrue($advancedPostalCodePatternColumnExists);

        $checkAdvancedPostalCodePatternColumnExists = $this->hasColumn('country', 'check_advanced_postal_code_pattern');
        static::assertTrue($checkAdvancedPostalCodePatternColumnExists);

        $checkPostalCodePatternColumnExists = $this->hasColumn('country', 'check_postal_code_pattern');
        static::assertTrue($checkPostalCodePatternColumnExists);

        $postalCodeRequiredColumnExists = $this->hasColumn('country', 'postal_code_required');
        static::assertTrue($postalCodeRequiredColumnExists);

        $addressFormatPlainColumnExists = $this->hasColumn('country_translation', 'advanced_address_format_plain');
        static::assertTrue($addressFormatPlainColumnExists);
    }

    private function prepare(): void
    {
        $useDefaultAddressFormatColumnExists = $this->hasColumn('country', 'use_default_address_format');

        if ($useDefaultAddressFormatColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country` DROP COLUMN `use_default_address_format`');
        }

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

        $addressFormatPlainColumnExists = $this->hasColumn('country_translation', 'advanced_address_format_plain');

        if ($addressFormatPlainColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `country_translation` DROP COLUMN `advanced_address_format_plain`');
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
