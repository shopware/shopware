<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1772007509ProductMainCategoryInheritance;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1772007509ProductMainCategoryInheritance::class)]
class Migration1772007509ProductMainCategoryInheritanceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1772007509, (new Migration1772007509ProductMainCategoryInheritance())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1772007509ProductMainCategoryInheritance();

        static::assertSame(1772007509, $migration->getCreationTimestamp());
    }

    public function testMigrationCreatesMainCategoriesColumn(): void
    {
        $this->rollback();

        $migration = new Migration1772007509ProductMainCategoryInheritance();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'product', 'mainCategories'));
    }

    private function rollback(): void
    {
        if (!TableHelper::columnExists($this->connection, 'product', 'mainCategories')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `mainCategories`');
    }
}
