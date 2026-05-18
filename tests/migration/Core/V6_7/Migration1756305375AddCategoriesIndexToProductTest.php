<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1756305375AddCategoriesIndexToProduct;
use Shopware\Tests\Migration\MySql84FkGuardTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1756305375AddCategoriesIndexToProduct::class)]
class Migration1756305375AddCategoriesIndexToProductTest extends TestCase
{
    use MySql84FkGuardTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1756305375, (new Migration1756305375AddCategoriesIndexToProduct())->getCreationTimestamp());
    }

    public function testIndexIsCreated(): void
    {
        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.categories')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.categories` ON `product`');
        }

        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.categories'));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1756305375AddCategoriesIndexToProduct();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.categories'));
    }

    /**
     * Regression coverage for issue #13039 / MySQL bug #118151. Skips outside
     * MySQL 8.4+ with `restrict_fk_on_non_standard_key=ON`.
     */
    public function testIndexIsCreatedOnMysql84WithNonStandardChildFkOnProduct(): void
    {
        $this->skipUnlessMysql84WithFkGuardOn($this->connection);

        if (TableHelper::indexExists($this->connection, 'product', 'idx.product.categories')) {
            $this->connection->executeStatement('DROP INDEX `idx.product.categories` ON `product`');
        }

        $this->createNonStandardChildFkOnProduct($this->connection);

        try {
            $this->runMigrationViaRuntime($this->connection, new Migration1756305375AddCategoriesIndexToProduct());

            static::assertTrue(TableHelper::indexExists($this->connection, 'product', 'idx.product.categories'));
            static::assertSame(
                '1',
                (string) $this->connection->fetchOne('SELECT @@SESSION.restrict_fk_on_non_standard_key'),
                'Runtime must restore the FK guard to its previous (ON) state'
            );
        } finally {
            $this->dropNonStandardChildFkOnProduct($this->connection);
        }
    }
}
