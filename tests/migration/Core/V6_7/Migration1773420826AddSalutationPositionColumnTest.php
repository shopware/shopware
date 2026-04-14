<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1773420826AddSalutationPositionColumn;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1773420826AddSalutationPositionColumn::class)]
class Migration1773420826AddSalutationPositionColumnTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigrationAddsColumnAndAssignsPositions(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->dropPositionColumnIfExists($connection);

        $migration = new Migration1773420826AddSalutationPositionColumn();
        $migration->update($connection);
        $migration->update($connection);

        $column = TableHelper::getColumnOfTable($connection, 'salutation', 'position');
        static::assertTrue($column->isNotNull);

        $positions = $connection->fetchAllKeyValue('SELECT salutation_key, position FROM salutation');

        static::assertSame('1', $positions['not_specified']);
        static::assertSame('2', $positions['mrs']);
        static::assertSame('3', $positions['mr']);
    }

    public function testMigrationDoesNotOverrideManualPositionsOnSecondRun(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->dropPositionColumnIfExists($connection);

        $migration = new Migration1773420826AddSalutationPositionColumn();
        $migration->update($connection);

        $connection->executeStatement(
            'UPDATE `salutation` SET `position` = 9 WHERE `salutation_key` = :key',
            ['key' => 'mr']
        );

        $migration->update($connection);

        $position = $connection->fetchOne('SELECT `position` FROM `salutation` WHERE `salutation_key` = "mr"');

        static::assertSame('9', $position);
    }

    private function dropPositionColumnIfExists(Connection $connection): void
    {
        if (TableHelper::columnExists($connection, 'salutation', 'position')) {
            $connection->executeStatement('ALTER TABLE `salutation` DROP COLUMN `position`');
        }
    }
}
