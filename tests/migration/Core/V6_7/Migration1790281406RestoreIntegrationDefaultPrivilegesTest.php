<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1790281406RestoreIntegrationDefaultPrivileges;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1790281406RestoreIntegrationDefaultPrivileges::class)]
class Migration1790281406RestoreIntegrationDefaultPrivilegesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->connection->rollBack();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790281406, (new Migration1790281406RestoreIntegrationDefaultPrivileges())->getCreationTimestamp());
    }

    public function testIntegrationRoleGetsMissingPrivilegesRestored(): void
    {
        $roleId = $this->createRole(['customer.viewer', 'customer:read', 'language:read']);
        $this->assignToIntegration($roleId);

        (new Migration1790281406RestoreIntegrationDefaultPrivileges())->update($this->connection);

        static::assertSame(
            ['customer.viewer', 'customer:read', 'language:read', 'locale:read', 'log_entry:create', 'message_queue_stats:read'],
            $this->privileges($roleId),
        );
        static::assertNotNull($this->connection->fetchOne('SELECT updated_at FROM acl_role WHERE id = :id', ['id' => $roleId]));
    }

    public function testPrivilegesTheRoleEditorNeverStoredAreNotAdded(): void
    {
        $roleId = $this->createRole(['customer:read']);
        $this->assignToIntegration($roleId);

        (new Migration1790281406RestoreIntegrationDefaultPrivileges())->update($this->connection);

        $privileges = $this->privileges($roleId);
        static::assertNotContains('currency:read', $privileges);
        static::assertNotContains('country:read', $privileges);
        static::assertNotContains('scheduled_task:read', $privileges);
    }

    public function testRoleWithoutIntegrationIsNotChanged(): void
    {
        $roleId = $this->createRole(['customer:read']);

        (new Migration1790281406RestoreIntegrationDefaultPrivileges())->update($this->connection);

        static::assertSame(['customer:read'], $this->privileges($roleId));
    }

    public function testAppRoleIsNotChanged(): void
    {
        $roleId = $this->createRole(['customer:read']);
        $integrationId = $this->assignToIntegration($roleId);
        $this->connection->insert('app', [
            'id' => Uuid::randomBytes(),
            'name' => 'TestApp' . Uuid::randomHex(),
            'version' => '1.0.0',
            'integration_id' => $integrationId,
            'acl_role_id' => $roleId,
            'created_at' => '2020-01-01 00:00:00',
        ]);

        (new Migration1790281406RestoreIntegrationDefaultPrivileges())->update($this->connection);

        static::assertSame(['customer:read'], $this->privileges($roleId));
    }

    public function testDeletedRoleIsNotChanged(): void
    {
        $roleId = $this->createRole(['customer:read']);
        $this->assignToIntegration($roleId);
        $this->connection->update('acl_role', ['deleted_at' => '2020-01-02 00:00:00'], ['id' => $roleId]);

        (new Migration1790281406RestoreIntegrationDefaultPrivileges())->update($this->connection);

        static::assertSame(['customer:read'], $this->privileges($roleId));
    }

    public function testMigrationIsIdempotent(): void
    {
        $roleId = $this->createRole(['customer:read', 'locale:read']);
        $this->assignToIntegration($roleId);

        $migration = new Migration1790281406RestoreIntegrationDefaultPrivileges();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            ['customer:read', 'locale:read', 'language:read', 'log_entry:create', 'message_queue_stats:read'],
            $this->privileges($roleId),
        );
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test-role-' . Uuid::randomHex(),
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => '2020-01-01 00:00:00',
        ]);

        return $roleId;
    }

    private function assignToIntegration(string $roleId): string
    {
        $integrationId = Uuid::randomBytes();
        $this->connection->insert('integration', [
            'id' => $integrationId,
            'label' => 'test-integration',
            'access_key' => 'SWIA' . Uuid::randomHex(),
            'secret_access_key' => 'secret',
            'created_at' => '2020-01-01 00:00:00',
        ]);
        $this->connection->insert('integration_role', [
            'integration_id' => $integrationId,
            'acl_role_id' => $roleId,
        ]);

        return $integrationId;
    }

    /**
     * @return list<string>
     */
    private function privileges(string $roleId): array
    {
        $json = $this->connection->fetchOne('SELECT privileges FROM acl_role WHERE id = :id', ['id' => $roleId]);

        /** @var list<string> $privileges */
        $privileges = json_decode((string) $json, true, flags: \JSON_THROW_ON_ERROR);

        return $privileges;
    }
}
