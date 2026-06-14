<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1765287397AddConsentTable;
use Shopware\Core\Migration\V6_7\Migration1770740885AddRevisionToConsentState;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(Migration1770740885AddRevisionToConsentState::class)]
class Migration1770740885AddRevisionToConsentStateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1770740885AddRevisionToConsentState();

        static::assertSame(1770740885, $migration->getCreationTimestamp());
    }

    public function testMigrationAddsRevisionColumn(): void
    {
        $this->ensureConsentStateTableExists();
        $this->rollback();

        $migration = new Migration1770740885AddRevisionToConsentState();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'consent_state', 'revision'));

        $column = $this->connection->fetchAssociative('SHOW COLUMNS FROM `consent_state` LIKE :column', [
            'column' => 'revision',
        ]);

        static::assertIsArray($column);
        static::assertSame('varchar(255)', strtolower((string) $column['Type']));
        static::assertSame('YES', $column['Null']);
        static::assertNull($column['Default']);
    }

    private function ensureConsentStateTableExists(): void
    {
        if (TableHelper::tableExists($this->connection, 'consent_state')) {
            return;
        }

        (new Migration1765287397AddConsentTable())->update($this->connection);
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, 'consent_state', 'revision')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `consent_state` DROP COLUMN `revision`;');
    }
}
