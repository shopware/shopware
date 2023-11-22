<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1676274910ChangeColumnTaxRateAllowThreeDecimal;

/**
 * @internal
 */
#[CoversClass(Migration1676274910ChangeColumnTaxRateAllowThreeDecimal::class)]
class Migration1676274910ChangeColumnTaxRateAllowThreeDecimalTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $sqlUpdateToTaxTable = <<<'SQL'
            ALTER TABLE tax
            MODIFY COLUMN `tax_rate` DECIMAL(10,2);
        SQL;

        $this->connection->executeStatement($sqlUpdateToTaxTable);

        $sqlUpdateToTaxRuleTable = <<<'SQL'
            ALTER TABLE tax_rule
            MODIFY COLUMN `tax_rate` DOUBLE(10,2);
        SQL;

        $this->connection->executeStatement($sqlUpdateToTaxRuleTable);
    }

    public function testChangeColumnTaxRateOnTaxTable(): void
    {
        $migration = new Migration1676274910ChangeColumnTaxRateAllowThreeDecimal();

        $migration->update($this->connection);

        $sql = <<<'SQL'
SELECT NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'tax'
AND COLUMN_NAME = 'tax_rate'
AND TABLE_SCHEMA = :dbName;
SQL;

        $dbName = $this->connection->getDatabase();

        /** @var array{NUMERIC_PRECISION: string, NUMERIC_SCALE: string, COLUMN_TYPE: string} $tableColumn */
        $tableColumn = $this->connection->fetchAssociative($sql, ['dbName' => $dbName]);

        static::assertEquals('10', $tableColumn['NUMERIC_PRECISION']);
        static::assertEquals('3', $tableColumn['NUMERIC_SCALE']);
        static::assertEquals('decimal(10,3)', $tableColumn['COLUMN_TYPE']);
    }

    public function testChangeColumnTaxRateOnTaxRuleTable(): void
    {
        $migration = new Migration1676274910ChangeColumnTaxRateAllowThreeDecimal();

        $migration->update($this->connection);

        $sql = <<<'SQL'
SELECT NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'tax_rule'
AND COLUMN_NAME = 'tax_rate'
AND TABLE_SCHEMA = :dbName;
SQL;

        $dbName = $this->connection->getDatabase();

        /** @var array{NUMERIC_PRECISION: string, NUMERIC_SCALE: string, COLUMN_TYPE: string} $tableColumn */
        $tableColumn = $this->connection->fetchAssociative($sql, ['dbName' => $dbName]);

        static::assertEquals('10', $tableColumn['NUMERIC_PRECISION']);
        static::assertEquals('3', $tableColumn['NUMERIC_SCALE']);
        static::assertEquals('double(10,3)', $tableColumn['COLUMN_TYPE']);
    }
}
