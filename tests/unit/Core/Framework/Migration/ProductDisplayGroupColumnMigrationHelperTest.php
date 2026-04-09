<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\ProductDisplayGroupColumnMigrationHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[CoversClass(ProductDisplayGroupColumnMigrationHelper::class)]
class ProductDisplayGroupColumnMigrationHelperTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->ensureDisplayGroupColumnVarchar64();

        parent::tearDown();
    }

    public function testWidensVarchar50To64AndSecondCallIsNoOp(): void
    {
        $this->narrowDisplayGroupColumnToVarchar50();

        ProductDisplayGroupColumnMigrationHelper::widenVarchar50To64ForSha256IfNeeded($this->connection);
        static::assertSame('varchar(64)', $this->getDisplayGroupColumnType());

        ProductDisplayGroupColumnMigrationHelper::widenVarchar50To64ForSha256IfNeeded($this->connection);
        static::assertSame('varchar(64)', $this->getDisplayGroupColumnType());
    }

    public function testNoOpWhenColumnAlready64(): void
    {
        $this->ensureDisplayGroupColumnVarchar64();

        ProductDisplayGroupColumnMigrationHelper::widenVarchar50To64ForSha256IfNeeded($this->connection);
        ProductDisplayGroupColumnMigrationHelper::widenVarchar50To64ForSha256IfNeeded($this->connection);

        static::assertSame('varchar(64)', $this->getDisplayGroupColumnType());
    }

    private function narrowDisplayGroupColumnToVarchar50(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(50) NULL');
    }

    private function ensureDisplayGroupColumnVarchar64(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');
    }

    private function getDisplayGroupColumnType(): string
    {
        $column = $this->connection->fetchAssociative(
            'SHOW COLUMNS FROM `product` LIKE :column',
            ['column' => 'display_group']
        );
        static::assertIsArray($column);

        return strtolower((string) $column['Type']);
    }
}
