<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Storefront\V6_5;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Storefront\Migration\V6_5\Migration1673616908AddThemeHash;

/**
 * @package core
 *
 * @internal
 *
 * @covers \Shopware\Storefront\Migration\V6_5\Migration1673616908AddThemeHash
 */
class Migration1673616908AddThemeHashTest extends TestCase
{
    public function testMigration(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1673616908AddThemeHash();
        // check that it can be executed when the column already exists
        $migration->update($connection);

        $exists = $connection->fetchOne('SHOW COLUMNS FROM `theme_sales_channel` WHERE `Field` LIKE "hash"');
        static::assertNotFalse($exists);

        $migration = new Migration1673616908AddThemeHash();
        // check that it can be executed when the column already exists
        $migration->update($connection);
    }
}
