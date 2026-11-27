<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1792654797AddOAuthResource;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792654797AddOAuthResource::class)]
class Migration1792654797AddOAuthResourceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792654797, (new Migration1792654797AddOAuthResource())->getCreationTimestamp());
    }

    public function testMigrationAddsResourceColumnsAndIsIdempotent(): void
    {
        foreach (['oauth_auth_code', 'refresh_token'] as $table) {
            if (TableHelper::columnExists($this->connection, $table, 'resource')) {
                $this->connection->executeStatement('ALTER TABLE `' . $table . '` DROP COLUMN `resource`');
            }
        }

        $migration = new Migration1792654797AddOAuthResource();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'oauth_auth_code', 'resource'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'refresh_token', 'resource'));
    }
}
