<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1774483201AddNewestProductSorting;

/**
 * @internal
 */
#[CoversClass(Migration1774483201AddNewestProductSorting::class)]
class Migration1774483201AddNewestProductSortingTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        // Remove 'newest' sorting if it exists
        $this->connection->executeStatement(
            'DELETE FROM product_sorting WHERE url_key = :key',
            ['key' => 'newest']
        );
    }

    public function testMigrationAddsNewestSorting(): void
    {
        $migration = new Migration1774483201AddNewestProductSorting();
        $migration->update($this->connection);

        $sorting = $this->connection->fetchAssociative(
            'SELECT * FROM product_sorting WHERE url_key = :key',
            ['key' => 'newest']
        );

        static::assertNotFalse($sorting);
        static::assertSame('newest', $sorting['url_key']);
        static::assertSame(1, (int) $sorting['active']);
        static::assertSame(0, (int) $sorting['locked']);
        static::assertSame(5, (int) $sorting['priority']);

        $fields = json_decode((string) $sorting['fields'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($fields);
        static::assertCount(1, $fields);
        static::assertSame('product.createdAt', $fields[0]['field']);
        static::assertSame('desc', $fields[0]['order']);
        static::assertSame(1, $fields[0]['priority']);
    }

    public function testMigrationDoesNotDuplicateIfSortingExists(): void
    {
        $migration = new Migration1774483201AddNewestProductSorting();

        // Run migration twice
        $migration->update($this->connection);
        $migration->update($this->connection);

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM product_sorting WHERE url_key = :key',
            ['key' => 'newest']
        );

        static::assertSame(1, (int) $count);
    }

    public function testMigrationAddsTranslations(): void
    {
        $migration = new Migration1774483201AddNewestProductSorting();
        $migration->update($this->connection);

        $sortingId = $this->connection->fetchOne(
            'SELECT id FROM product_sorting WHERE url_key = :key',
            ['key' => 'newest']
        );

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_sorting_translation WHERE product_sorting_id = :id',
            ['id' => $sortingId]
        );

        static::assertCount(2, $translations);

        $labels = array_column($translations, 'label');
        static::assertContains('Newest', $labels);
        static::assertContains('Neueste', $labels);
    }
}
