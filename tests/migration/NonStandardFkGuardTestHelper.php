<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
final class NonStandardFkGuardTestHelper
{
    public static function assertIndexCreationSurvivesNonStandardForeignKeyGuard(TestCase $test, MigrationStep $migration, string $table): void
    {
        $tableSchema = (new MockBuilder($test, Table::class))->disableOriginalConstructor()->getMock();
        $tableSchema->method('hasIndex')->willReturn(false);

        $schemaManager = (new MockBuilder($test, AbstractSchemaManager::class))->disableOriginalConstructor()->getMock();
        $schemaManager->method('introspectTableByUnquotedName')->willReturn($tableSchema);

        $failure = new class(1553, 'Cannot drop index \'<unknown key name>\': needed in a foreign key constraint') extends DriverException {
            public function __construct(int $errorCode, string $message)
            {
                $this->code = $errorCode;
                $this->message = $message;
            }
        };

        $statements = [];
        $connection = (new MockBuilder($test, Connection::class))->disableOriginalConstructor()->getMock();
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

        Assert::assertCount(4, $statements);
        Assert::assertStringStartsWith('CREATE INDEX', $statements[0]);
        Assert::assertStringContainsString(\sprintf('ON `%s`', $table), $statements[0]);
        Assert::assertSame('SET SESSION restrict_fk_on_non_standard_key = OFF', $statements[1]);
        Assert::assertSame($statements[0], $statements[2]);
        Assert::assertSame('SET SESSION restrict_fk_on_non_standard_key = ON', $statements[3]);
    }
}
