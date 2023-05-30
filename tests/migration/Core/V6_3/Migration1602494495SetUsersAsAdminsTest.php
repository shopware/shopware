<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1602494495SetUsersAsAdmins;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1602494495SetUsersAsAdmins
 */
class Migration1602494495SetUsersAsAdminsTest extends TestCase
{
    public function testAtLeastOneAdmin(): void
    {
        $conn = KernelLifecycleManager::getConnection();

        $conn->executeStatement('UPDATE `user` SET `admin` = 0');

        $migration = new Migration1602494495SetUsersAsAdmins();
        $migration->update($conn);

        $adminUsers = (int) $conn->fetchOne('SELECT COUNT(*) FROM `user`');
        static::assertTrue($adminUsers >= 1, 'Minimum one user admin user should be registered, non found');
    }
}
