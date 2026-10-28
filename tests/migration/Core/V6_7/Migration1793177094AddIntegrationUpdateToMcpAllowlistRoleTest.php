<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1793177094AddIntegrationUpdateToMcpAllowlistRole;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1793177094AddIntegrationUpdateToMcpAllowlistRole::class)]
class Migration1793177094AddIntegrationUpdateToMcpAllowlistRoleTest extends TestCase
{
    private Connection $connection;

    private Migration1793177094AddIntegrationUpdateToMcpAllowlistRole $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1793177094AddIntegrationUpdateToMcpAllowlistRole();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793177094, $this->migration->getCreationTimestamp());
    }

    public function testAddsIntegrationUpdateToMcpAllowlistEditors(): void
    {
        $roleId = $this->createRole(['integration_mcp.editor', 'api_action_integration_mcp-allowlist']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('integration:update', $privileges);
        static::assertCount(1, \array_keys($privileges, 'integration:update', true));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['integration.viewer', 'integration:read']);
        $roleBeforeMigration = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $roleAfterMigration = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($roleBeforeMigration, $roleAfterMigration);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test mcp allowlist acl migration',
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    /**
     * @return list<string>
     */
    private function fetchPrivileges(string $roleId): array
    {
        $privilegesJson = $this->connection->fetchOne(
            'SELECT `privileges` FROM `acl_role` WHERE id = :id',
            ['id' => $roleId]
        );

        static::assertIsString($privilegesJson);

        $decodedPrivileges = \json_decode($privilegesJson, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decodedPrivileges);
        static::assertTrue(\array_is_list($decodedPrivileges));
        foreach ($decodedPrivileges as $decodedPrivilege) {
            static::assertIsString($decodedPrivilege);
        }

        /** @var list<string> $decodedPrivileges */
        return $decodedPrivileges;
    }
}
