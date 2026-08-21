<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787220016AddOwnerToWebhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787220016AddOwnerToWebhook::class)]
class Migration1787220016AddOwnerToWebhookTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787220016, (new Migration1787220016AddOwnerToWebhook())->getCreationTimestamp());
    }

    public function testMigrationAddsColumns(): void
    {
        $migration = new Migration1787220016AddOwnerToWebhook();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook', 'owner_user_id'));
        static::assertTrue(TableHelper::columnExists($this->connection, 'webhook', 'owner_integration_id'));
    }

    public function testMigrationDeactivatesAppLessWebhooksWithoutOwner(): void
    {
        (new Migration1787220016AddOwnerToWebhook())->update($this->connection);

        $userId = $this->createUser();
        $ownerlessId = Uuid::randomBytes();
        $ownedByUserId = Uuid::randomBytes();

        $this->insertWebhook($ownerlessId, 'ownerless', null);
        $this->insertWebhook($ownedByUserId, 'owned-by-user', $userId);

        try {
            (new Migration1787220016AddOwnerToWebhook())->update($this->connection);

            static::assertFalse($this->fetchActive($ownerlessId));
            static::assertTrue($this->fetchActive($ownedByUserId));
        } finally {
            $this->connection->delete('webhook', ['id' => $ownerlessId]);
            $this->connection->delete('webhook', ['id' => $ownedByUserId]);
            $this->connection->delete('user', ['id' => Uuid::fromHexToBytes($userId)]);
        }
    }

    private function insertWebhook(string $id, string $name, ?string $ownerUserId): void
    {
        $this->connection->insert('webhook', [
            'id' => $id,
            'name' => $name,
            'event_name' => 'product.written',
            'url' => 'https://example.com',
            'active' => 1,
            'owner_user_id' => $ownerUserId ? Uuid::fromHexToBytes($ownerUserId) : null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function fetchActive(string $id): bool
    {
        return (bool) $this->connection->fetchOne('SELECT active FROM webhook WHERE id = :id', ['id' => $id]);
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $localeId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1');

        $this->connection->insert('user', [
            'id' => Uuid::fromHexToBytes($userId),
            'first_name' => 'webhook',
            'last_name' => 'owner',
            'email' => 'owner-' . $userId . '@example.com',
            'username' => 'owner-' . $userId,
            'password' => password_hash('shopware', \PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes($localeId),
            'active' => 1,
            'admin' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $userId;
    }
}
