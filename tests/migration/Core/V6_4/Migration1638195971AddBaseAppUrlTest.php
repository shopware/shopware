<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1638195971AddBaseAppUrl;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1635237551Script
 */
class Migration1638195971AddBaseAppUrlTest extends TestCase
{
    public function testColumnIsPresent(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('ALTER TABLE `app` DROP COLUMN `base_app_url`;');

        $migration = new Migration1638195971AddBaseAppUrl();
        $migration->update($connection);

        $appColumns = array_column($connection->fetchAllAssociative('SHOW COLUMNS FROM app'), 'Field');

        static::assertContains('base_app_url', $appColumns);
    }
}
