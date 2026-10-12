<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1791772236AddUnitIsoCode;
use Shopware\Core\System\Unit\UnitDefinition;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1791772236AddUnitIsoCode::class)]
class Migration1791772236AddUnitIsoCodeTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791772236, (new Migration1791772236AddUnitIsoCode())->getCreationTimestamp());
    }

    public function testMigrationAddsNullableIsoCodeColumnAndIsIdempotent(): void
    {
        if (TableHelper::columnExists($this->connection, 'unit', 'iso_code')) {
            $this->connection->executeStatement('ALTER TABLE `unit` DROP COLUMN `iso_code`');
        }

        $migration = new Migration1791772236AddUnitIsoCode();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection
            ->createSchemaManager()
            ->introspectTableByUnquotedName(UnitDefinition::ENTITY_NAME)
            ->getColumn('iso_code');

        static::assertFalse($column->getNotnull());
        static::assertSame(3, $column->getLength());
    }
}
