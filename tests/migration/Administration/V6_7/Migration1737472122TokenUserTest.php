<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_7\Migration1737472122TokenUser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[Package('after-sales')]
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

    private function tableExists(): bool
    {
        try {
            $this->connection->fetchOne('SELECT * FROM `token_user` LIMIT 1');
        } catch (Exception) {
            return false;
        }

        return true;
    }

    public function dropTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `token_user`');
    }
}
