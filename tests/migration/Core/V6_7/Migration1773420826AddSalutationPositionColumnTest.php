<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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

        $columnDefinition = $connection->fetchAssociative('SHOW COLUMNS FROM `salutation` LIKE "position"');
        static::assertNotFalse($columnDefinition);
        static::assertSame('NO', $columnDefinition['Null']);

        $positions = $connection->fetchAllKeyValue('SELECT salutation_key, position FROM salutation');

        static::assertSame('1', $positions['not_specified']);
        static::assertSame('2', $positions['mrs']);
        static::assertSame('3', $positions['mr']);

        static::assertArrayHasKey('diverse', $positions);
        static::assertSame('1', $positions['diverse']);
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
        try {
            $connection->executeStatement('ALTER TABLE `salutation` DROP COLUMN `position`');
        } catch (\Throwable $e) {
            // column didn't exist – ignore
        }
    }
}
