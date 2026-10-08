<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('framework')]
abstract class NonStandardFkGuardMigrationTestCase extends TestCase
{
    protected function assertIndexCreationSurvivesNonStandardForeignKeyGuard(MigrationStep $migration, string $table): void
    {
        $tableSchema = static::createStub(Table::class);
        $tableSchema->method('hasIndex')->willReturn(false);

        $schemaManager = static::createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectTableByUnquotedName')->willReturn($tableSchema);

        $failure = new class(1553, 'Cannot drop index \'<unknown key name>\': needed in a foreign key constraint') extends DriverException {
            public function __construct(int $errorCode, string $message)
            {
                $this->code = $errorCode;
                $this->message = $message;
            }
        };

        $statements = [];
        $connection = static::createStub(Connection::class);
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
}
