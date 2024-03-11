<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1689776940AddCartSourceField;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Migration1689776940AddCartSourceField::class)]
class Migration1689776940AddCartSourceFieldTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1689776940AddCartSourceField();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'source'));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1689776940AddCartSourceField();

        if ($this->columnExists($connection, 'plugin_id')) {
            $connection->executeStatement('ALTER TABLE `order` DROP `source`;');
        }
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'source'));
    }

    private function columnExists(Connection $connection, string $column): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `order` WHERE `Field` LIKE :column;',
            ['column' => $column]
        );

        return $field === $column;
    }
}
