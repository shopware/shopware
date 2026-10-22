<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index\IndexType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\Index;
use Shopware\Core\Framework\Util\Database\TableHelper;
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

        $table = TableHelper::getTable($this->connection, 'oauth_auth_code');
        static::assertSame(['id', 'code_id', 'user_id', 'client_id', 'issued_at', 'expires_at'], array_column($table->columns, 'name'));

        $uniqueIndexes = array_column(
            array_filter(
                $table->indexes,
                static fn (Index $index) => $index->type === IndexType::UNIQUE->name
            ),
            'name'
        );
        static::assertEqualsCanonicalizing(['primary', 'uniq.oauth_auth_code.code_id'], $uniqueIndexes);
    }
}
