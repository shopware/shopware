<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_3\Migration1583844433AddRefreshTokenTable;
use Shopware\Core\Migration\V6_7\Migration1793357402AddRefreshTokenUserIdIndex;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793357402AddRefreshTokenUserIdIndex::class)]
class Migration1793357402AddRefreshTokenUserIdIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793357402, (new Migration1793357402AddRefreshTokenUserIdIndex())->getCreationTimestamp());
    }

    public function testMigrationCreatesUserIdIndexAndIsIdempotent(): void
    {
        (new Migration1583844433AddRefreshTokenTable())->update($this->connection);
        if (TableHelper::indexExists($this->connection, 'refresh_token', 'idx.refresh_token.user_id')) {
            $this->connection->executeStatement('DROP INDEX `idx.refresh_token.user_id` ON `refresh_token`');
        }

        $migration = new Migration1793357402AddRefreshTokenUserIdIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'refresh_token', 'idx.refresh_token.user_id'));
        static::assertTrue(TableHelper::indexSpansColumns(
            $this->connection,
            'refresh_token',
            'idx.refresh_token.user_id',
            ['user_id'],
        ));
    }
}
