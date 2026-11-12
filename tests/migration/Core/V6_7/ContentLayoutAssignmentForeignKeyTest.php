<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1758018342ProductContentLayout;
use Shopware\Core\Migration\V6_7\Migration1758018343CategoryContentLayout;
use Shopware\Core\Migration\V6_7\Migration1758018344LandingPageContentLayout;
use Shopware\Core\Migration\V6_7\Migration1758018345HeaderContentLayout;
use Shopware\Core\Migration\V6_7\Migration1758018346FooterContentLayout;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1758018342ProductContentLayout::class)]
#[CoversClass(Migration1758018343CategoryContentLayout::class)]
#[CoversClass(Migration1758018344LandingPageContentLayout::class)]
#[CoversClass(Migration1758018345HeaderContentLayout::class)]
#[CoversClass(Migration1758018346FooterContentLayout::class)]
class ContentLayoutAssignmentForeignKeyTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @param class-string<MigrationStep> $migrationClass
     * @param list<string> $siblingForeignKeys
     */
    #[DataProvider('assignmentMigrationProvider')]
    #[TestDox('the content_layout_id foreign key of $_dataName restricts layout deletion while its sibling foreign keys still cascade')]
    public function testContentLayoutForeignKeyRestrictsDeletionWhileSiblingsCascade(
        string $migrationClass,
        string $table,
        string $contentLayoutForeignKey,
        array $siblingForeignKeys,
    ): void {
        $this->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', $table));

        $migration = new $migrationClass();
        $migration->update($this->connection);
        $migration->update($this->connection);

        // RESTRICT and NO ACTION are equivalent "block the delete" rules in MySQL; both satisfy the requirement
        // that a bound layout cannot be removed at the database level.
        static::assertContains($this->deleteRule($contentLayoutForeignKey), ['RESTRICT', 'NO ACTION']);
        static::assertSame(array_fill_keys($siblingForeignKeys, 'CASCADE'), $this->deleteRules($siblingForeignKeys));
    }

    /**
     * @return iterable<string, array{class-string<MigrationStep>, string, string, list<string>}>
     */
    public static function assignmentMigrationProvider(): iterable
    {
        yield 'product_content_layout' => [
            Migration1758018342ProductContentLayout::class,
            'product_content_layout',
            'fk.product_content_layout.content_layout_id',
            ['fk.product_content_layout.sales_channel_id'],
        ];
        yield 'category_content_layout' => [
            Migration1758018343CategoryContentLayout::class,
            'category_content_layout',
            'fk.category_content_layout.content_layout_id',
            ['fk.category_content_layout.sales_channel_id'],
        ];
        yield 'landing_page_content_layout' => [
            Migration1758018344LandingPageContentLayout::class,
            'landing_page_content_layout',
            'fk.landing_page_content_layout.content_layout_id',
            ['fk.landing_page_content_layout.sales_channel_id'],
        ];
        yield 'header_content_layout' => [
            Migration1758018345HeaderContentLayout::class,
            'header_content_layout',
            'fk.header_content_layout.content_layout_id',
            ['fk.header_content_layout.domain_id', 'fk.header_content_layout.sales_channel_id'],
        ];
        yield 'footer_content_layout' => [
            Migration1758018346FooterContentLayout::class,
            'footer_content_layout',
            'fk.footer_content_layout.content_layout_id',
            ['fk.footer_content_layout.domain_id', 'fk.footer_content_layout.sales_channel_id'],
        ];
    }

    /**
     * @param list<string> $constraintNames
     *
     * @return array<string, string>
     */
    private function deleteRules(array $constraintNames): array
    {
        $rules = [];
        foreach ($constraintNames as $name) {
            $rules[$name] = $this->deleteRule($name);
        }

        return $rules;
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
