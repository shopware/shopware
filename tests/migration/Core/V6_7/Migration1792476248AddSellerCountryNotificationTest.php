<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792476248AddSellerCountryNotification;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1792476248AddSellerCountryNotification::class)]
class Migration1792476248AddSellerCountryNotificationTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->deleteNotification();
    }

    protected function tearDown(): void
    {
        $this->deleteNotification();

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792476248, (new Migration1792476248AddSellerCountryNotification())->getCreationTimestamp());
    }

    public function testUpdateCreatesTheNotificationOnce(): void
    {
        $migration = new Migration1792476248AddSellerCountryNotification();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $notifications = $this->fetchNotifications();

        static::assertCount(1, $notifications);
        static::assertSame('warning', $notifications[0]['status']);
        static::assertSame('sw-settings-basic-information.sellerCountryNotification.message', $notifications[0]['message']);
        static::assertSame(0, (int) $notifications[0]['admin_only']);
        static::assertSame(['system.system_config'], json_decode((string) $notifications[0]['required_privileges'], true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testUpdateSkipsShopsWithoutUsers(): void
    {
        $this->connection->beginTransaction();

        try {
            $this->connection->executeStatement('DELETE FROM `user`');

            (new Migration1792476248AddSellerCountryNotification())->update($this->connection);

            static::assertCount(0, $this->fetchNotifications());
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchNotifications(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT `status`, `message`, `admin_only`, `required_privileges` FROM `notification` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes(Migration1792476248AddSellerCountryNotification::NOTIFICATION_ID)]
        );
    }

    private function deleteNotification(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `notification` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes(Migration1792476248AddSellerCountryNotification::NOTIFICATION_ID)]
        );
    }
}
