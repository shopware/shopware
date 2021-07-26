<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1623305620ChangeSalutationIdNullable as MigrationTested;

class Migration1623305620ChangeSalutationIdNullableTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    public function setUp(): void
    {
        parent::setup();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @before
     */
    public function initialise(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->rollBack();

        (new MigrationTested())->update($connection);

        $connection->beginTransaction();
    }

    public function testSalutationColumnsAreNullable(): void
    {
        $schema = $this->connection->getSchemaManager();

        /** @var array<Column[]> $columns */
        $columns = array_map(static function (string $table) use ($schema): array {
            return $schema->listTableColumns($table);
        }, MigrationTested::TABLES);

        $columns = array_filter(array_merge(...$columns), static function (Column $column): bool {
            return $column->getName() === 'salutation_id';
        });

        foreach ($columns as $column) {
            static::assertFalse($column->getNotnull());
        }
    }
}
