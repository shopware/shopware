<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1760438732AddConsumedToPaymentToken;

/**
 * @internal
 */
#[CoversClass(Migration1760438732AddConsumedToPaymentToken::class)]
class Migration1760438732AddConsumedToPaymentTokenTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1760438732AddConsumedToPaymentToken();
        static::assertSame(1760438732, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $migration = new Migration1760438732AddConsumedToPaymentToken();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('payment_token');
        static::assertArrayHasKey('consumed', $existingColumns);
    }

    private function rollback(): void
    {
        $existingColumns = $this->connection->createSchemaManager()->listTableColumns('payment_token');

        if (\array_key_exists('consumed', $existingColumns)) {
            $this->connection->executeStatement('ALTER TABLE `payment_token` DROP COLUMN `consumed`;');
        }
    }
}
