<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_4\Migration1632281097Notification;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class Migration1632281097NotificationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoNotificationTable(): void
    {
        $conn = $this->getContainer()->get(Connection::class);
        $conn->executeStatement('DROP TABLE `notification`');

        $migration = new Migration1632281097Notification();
        $migration->update($conn);
        $exists = $conn->fetchOne('SELECT COUNT(*) FROM `notification`') !== false;

        static::assertTrue($exists);
    }
}
