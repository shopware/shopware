<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1747746986OrderTaxCalculationType;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1747746986OrderTaxCalculationType::class)]
class Migration1747746986OrderTaxCalculationTypeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testAddedColumn(): void
    {
        if ($this->columnExists()) {
            $this->rollback();
        }

        $migration = new Migration1747746986OrderTaxCalculationType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->columnExists());
    }

    public function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `order` DROP COLUMN `tax_calculation_type`');
    }

    protected function columnExists(): bool
    {
        $exists = $this->connection->fetchOne(
            'SHOW COLUMNS FROM `order` WHERE `Field` LIKE "tax_calculation_type"',
        );

        return !empty($exists);
    }
}
