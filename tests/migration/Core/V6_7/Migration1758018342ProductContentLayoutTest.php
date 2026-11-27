<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1758018342ProductContentLayout;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1758018342ProductContentLayout::class)]
class Migration1758018342ProductContentLayoutTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    #[TestDox('content_layout_id foreign key restricts layout deletion while its sibling foreign key still cascades')]
    public function testContentLayoutForeignKeyRestrictsDeletionWhileSiblingCascades(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `product_content_layout`');

        $migration = new Migration1758018342ProductContentLayout();
        $migration->update($this->connection);
        $migration->update($this->connection);

        // RESTRICT and NO ACTION are equivalent "block the delete" rules in MySQL; both satisfy the requirement
        // that a bound layout cannot be removed at the database level.
        static::assertContains($this->deleteRule('fk.product_content_layout.content_layout_id'), ['RESTRICT', 'NO ACTION']);
        static::assertSame('CASCADE', $this->deleteRule('fk.product_content_layout.sales_channel_id'));
    }

    private function deleteRule(string $constraintName): string
    {
        $rule = $this->connection->fetchOne(
            'SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :name',
            ['name' => $constraintName]
        );

        static::assertIsString($rule, \sprintf('Foreign key "%s" was not found.', $constraintName));

        return $rule;
    }
}
