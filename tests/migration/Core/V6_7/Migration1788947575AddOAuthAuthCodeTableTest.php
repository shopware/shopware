<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1788947575AddOAuthAuthCodeTable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1788947575AddOAuthAuthCodeTable::class)]
class Migration1788947575AddOAuthAuthCodeTableTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788947575, (new Migration1788947575AddOAuthAuthCodeTable())->getCreationTimestamp());
    }

    public function testMigrationCreatesTableAndIsIdempotent(): void
    {
        $migration = new Migration1788947575AddOAuthAuthCodeTable();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `oauth_auth_code`'), 'Field');
        static::assertSame(['id', 'code_id', 'user_id', 'client_id', 'issued_at', 'expires_at'], $columns);

        $uniqueIndexes = array_unique(array_column(
            array_filter(
                $this->connection->fetchAllAssociative('SHOW INDEX FROM `oauth_auth_code`'),
                static fn (array $index) => (int) $index['Non_unique'] === 0
            ),
            'Key_name'
        ));
        static::assertEqualsCanonicalizing(['PRIMARY', 'uniq.oauth_auth_code.code_id'], array_values($uniqueIndexes));
    }
}
