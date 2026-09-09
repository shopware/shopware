<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern::class)]
class Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePatternTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern();
        static::assertSame(1788950275, $migration->getCreationTimestamp());
    }

    public function testMigrationChangesColumnLengthAndIsIdempotent(): void
    {
        $migration = new Migration1788950275ExtendLengthOfCountryAdvancedPostalCodePattern();

        // Set column to original size to test the migration properly, as test DB may already have VARCHAR(1024)
        $this->connection->executeStatement('
            ALTER TABLE `country`
            MODIFY COLUMN `advanced_postal_code_pattern` VARCHAR(255) NULL
        ');

        $column = TableHelper::getColumnOfTable($this->connection, 'country', 'advanced_postal_code_pattern');
        static::assertSame(Types::STRING, $column->type);
        static::assertSame(255, $column->length);

        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'country', 'advanced_postal_code_pattern');
        static::assertSame(Types::STRING, $column->type);
        static::assertSame(1024, $column->length);
        static::assertFalse($column->isNotNull);

        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'country', 'advanced_postal_code_pattern');
        static::assertSame(Types::STRING, $column->type);
        static::assertSame(1024, $column->length);
        static::assertFalse($column->isNotNull);
    }
}
