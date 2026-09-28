<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787220016AddOwnerToWebhook;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787220016AddOwnerToWebhook::class)]
class Migration1787220016AddOwnerToWebhookTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestTrait;

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

    public function testMigrationAssignsAppLessWebhooksWithoutOwnerToOldestAdmin(): void
    {
        (new Migration1787220016AddOwnerToWebhook())->update($this->connection);

        $oldestAdminId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM user WHERE username = :username', ['username' => 'admin']);
        TestUser::createNewAdminTestUser($this->connection);
        $userId = TestUser::createNewTestUser($this->connection)->getUserId();
        $ownerlessId = Uuid::randomBytes();
        $ownedByUserId = Uuid::randomBytes();

        $this->insertWebhook($ownerlessId, 'ownerless', null);
        $this->insertWebhook($ownedByUserId, 'owned-by-user', $userId);

        (new Migration1787220016AddOwnerToWebhook())->update($this->connection);

        static::assertEquals([
            Uuid::fromBytesToHex($ownerlessId) => ['active' => 1, 'owner_user_id' => $oldestAdminId],
            Uuid::fromBytesToHex($ownedByUserId) => ['active' => 1, 'owner_user_id' => $userId],
        ], $this->fetchWebhooks([$ownerlessId, $ownedByUserId]));
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

    /**
     * @param list<string> $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function fetchWebhooks(array $ids): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(id)), active, LOWER(HEX(owner_user_id)) AS owner_user_id FROM webhook WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
