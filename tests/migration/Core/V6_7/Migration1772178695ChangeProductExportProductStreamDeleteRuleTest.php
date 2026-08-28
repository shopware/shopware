<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\ForeignKeyConstraint\ReferentialAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1772178695ChangeProductExportProductStreamDeleteRule;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1772178695ChangeProductExportProductStreamDeleteRule::class)]
class Migration1772178695ChangeProductExportProductStreamDeleteRuleTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->dropProductStreamForeignKey();
        $this->connection->executeStatement(
            <<<'SQL'
            ALTER TABLE `product_export`
                ADD CONSTRAINT `fk.product_export.product_stream_id`
                    FOREIGN KEY (`product_stream_id`) REFERENCES `product_stream` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
SQL
        );
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1772178695, (new Migration1772178695ChangeProductExportProductStreamDeleteRule())->getCreationTimestamp());
    }

    public function testMigrationChangesDeleteRuleToRestrict(): void
    {
        $migration = new Migration1772178695ChangeProductExportProductStreamDeleteRule();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $fks = $this->connection->createSchemaManager()->introspectTableForeignKeyConstraintsByQuotedName('product_export');
        $fk = current(array_filter($fks, fn (ForeignKeyConstraint $fk) => $fk->getObjectName() !== null && $fk->getObjectName()->toSQL($this->connection->getDatabasePlatform()) === '`fk.product_export.product_stream_id`'));

        static::assertInstanceOf(ForeignKeyConstraint::class, $fk);
        static::assertContains(
            $fk->getOnDeleteAction(),
            [ReferentialAction::RESTRICT, ReferentialAction::NO_ACTION]
        );
    }

    public function testTimestampIsCorrect(): void
    {
        $migration = new Migration1772178695ChangeProductExportProductStreamDeleteRule();
        static::assertSame(1772178695, $migration->getCreationTimestamp());
    }

    private function dropProductStreamForeignKey(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `product_export` DROP FOREIGN KEY `fk.product_export.product_stream_id`;'
            );
        } catch (\Throwable) {
        }
    }
}
