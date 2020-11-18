<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1602494495SetUsersAsAdminsTest extends TestCase
{
    use KernelTestBehaviour;

    public function testAtLeastOneAdmin(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $adminUsers = $conn->fetchAssoc('SELECT * FROM `user`');
        static::assertTrue(count($adminUsers) >= 1, 'Minimum one user admin user should be registered, non found');
    }
}
