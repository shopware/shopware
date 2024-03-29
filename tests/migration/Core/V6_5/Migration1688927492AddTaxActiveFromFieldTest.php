<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1688927492AddTaxActiveFromField;

/**
 * @internal
 */
#[CoversClass(Migration1688927492AddTaxActiveFromField::class)]
class Migration1688927492AddTaxActiveFromFieldTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1688927492AddTaxActiveFromField();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1688927492AddTaxActiveFromField();

        if ($this->columnExists($connection)) {
            $connection->executeStatement('ALTER TABLE `tax_rule` DROP `active_from`;');
        }
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection));
    }

    private function columnExists(Connection $connection): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `tax_rule` WHERE `Field` LIKE :column;',
            ['column' => 'active_from']
        );

        return $field === 'active_from';
    }
}
