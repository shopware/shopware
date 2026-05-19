<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1741163941AddOrderInternalComment;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1741163941AddOrderInternalComment::class)]
class Migration1741163941AddOrderInternalCommentTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1741163941, (new Migration1741163941AddOrderInternalComment())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $migration = new Migration1741163941AddOrderInternalComment();
        $migration->update($connection);
        $migration->update($connection);

        $column = TableHelper::getColumnOfTable($connection, 'order', 'internal_comment');
        static::assertFalse($column->isNotNull);
    }

    private function revertMigration(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `order` DROP COLUMN `internal_comment`');
    }
}
