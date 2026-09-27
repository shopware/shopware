<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\V6_7\Migration1752750086AddIndexToOrderLineItemCreateAndUpdate;
use Shopware\Core\Migration\V6_7\Migration1752750171AddIndexToOrderAddressCreateAndUpdate;
use Shopware\Core\Migration\V6_7\Migration1752750234AddIndexToOrderTransactionCreateAndUpdate;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1752750086AddIndexToOrderLineItemCreateAndUpdate::class)]
#[CoversClass(Migration1752750171AddIndexToOrderAddressCreateAndUpdate::class)]
#[CoversClass(Migration1752750234AddIndexToOrderTransactionCreateAndUpdate::class)]
class CreatedUpdatedIndexMigrationsNonStandardFkGuardTest extends TestCase
{
    private const ER_DROP_INDEX_FK = 1553;

    #[DataProvider('migrationProvider')]
    public function testIndexCreationSurvivesNonStandardForeignKeyGuard(MigrationStep $migration, string $table): void
    {
        $tableSchema = $this->createMock(Table::class);
        $tableSchema->method('hasIndex')->willReturn(false);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->method('introspectTableByUnquotedName')->willReturn($tableSchema);

        $failure = new class(self::ER_DROP_INDEX_FK, 'Cannot drop index \'<unknown key name>\': needed in a foreign key constraint') extends DriverException {
            public function __construct(int $errorCode, string $message)
            {
                $this->code = $errorCode;
                $this->message = $message;
            }
        };

        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('fetchAssociative')->willReturn(['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'ON']);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements, $failure): int {
            $statements[] = $sql;

            if (\count($statements) === 1) {
                throw $failure;
            }

            return 0;
        });

        $migration->update($connection);

        static::assertCount(4, $statements);
        static::assertStringStartsWith('CREATE INDEX', $statements[0]);
        static::assertStringContainsString(\sprintf('ON `%s`', $table), $statements[0]);
        static::assertSame('SET SESSION restrict_fk_on_non_standard_key = OFF', $statements[1]);
        static::assertSame($statements[0], $statements[2]);
        static::assertSame('SET SESSION restrict_fk_on_non_standard_key = ON', $statements[3]);
    }

    /**
     * @return iterable<string, array{MigrationStep, string}>
     */
    public static function migrationProvider(): iterable
    {
        yield 'order_line_item' => [new Migration1752750086AddIndexToOrderLineItemCreateAndUpdate(), 'order_line_item'];
        yield 'order_address' => [new Migration1752750171AddIndexToOrderAddressCreateAndUpdate(), 'order_address'];
        yield 'order_transaction' => [new Migration1752750234AddIndexToOrderTransactionCreateAndUpdate(), 'order_transaction'];
    }
}
