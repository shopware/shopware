<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_7\Migration1737472122TokenUser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1737472122TokenUser::class)]
class Migration1737472122TokenUserTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        if ($this->tableExists()) {
            $this->dropTable();
        }

        static::assertFalse($this->tableExists());

        $migration = new Migration1737472122TokenUser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->tableExists());
    }

    public function dropTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `oauth_user`');
    }

    private function tableExists(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist(['oauth_user']);
    }
}
