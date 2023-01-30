<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1623305620ChangeSalutationIdNullable as MigrationTested;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1623305620ChangeSalutationIdNullable
 */
class Migration1623305620ChangeSalutationIdNullableTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->rollBack();

        (new MigrationTested())->update($connection);

        $connection->beginTransaction();
    }

    public function testSalutationColumnsAreNullable(): void
    {
        $schema = $this->connection->getSchemaManager();

        /** @var array<Column[]> $columns */
        $columns = array_map(static fn (string $table): array => $schema->listTableColumns($table), MigrationTested::TABLES);

        $columns = array_filter(array_merge(...$columns), static fn (Column $column): bool => $column->getName() === 'salutation_id');

        foreach ($columns as $column) {
            static::assertFalse($column->getNotnull());
        }
    }
}
