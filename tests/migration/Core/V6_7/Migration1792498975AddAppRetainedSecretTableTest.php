<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1792498975AddAppRetainedSecretTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792498975AddAppRetainedSecretTable::class)]
class Migration1792498975AddAppRetainedSecretTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792498975, (new Migration1792498975AddAppRetainedSecretTable())->getCreationTimestamp());
    }

    public function testAddTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_retained_secret`');

        static::assertFalse($this->tableExists());

        $migration = new Migration1792498975AddAppRetainedSecretTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->tableExists());
    }

    private function tableExists(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist(['app_retained_secret']);
    }
}
