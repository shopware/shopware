<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\ContextGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1783691346AddAppCapabilityPermissions;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1783691346AddAppCapabilityPermissions::class)]
class Migration1783691346AddAppCapabilityPermissionsTest extends TestCase
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
        static::assertSame(1783691346, (new Migration1783691346AddAppCapabilityPermissions())->getCreationTimestamp());
    }

    public function testConsentedAppGetsCapabilityGranted(): void
    {
        // fully consented app (nothing still requested) that declares a checkout gateway
        $appId = $this->createApp(rolePrivileges: ['product:read'], requestedPrivileges: [], columns: [
            'checkout_gateway_url' => 'https://example.com/checkout',
        ]);

        (new Migration1783691346AddAppCapabilityPermissions())->update($this->connection);

        static::assertContains(CheckoutGateway::PERMISSION, $this->grantedPrivileges($appId));
        static::assertNotContains(CheckoutGateway::PERMISSION, $this->requestedPrivileges($appId));
    }

    public function testPendingServiceGetsCapabilityRequested(): void
    {
        // service still pending consent (has requested privileges) that declares a context gateway
        $appId = $this->createApp(rolePrivileges: [], requestedPrivileges: ['customer:read'], columns: [
            'context_gateway_url' => 'https://example.com/context',
        ]);

        (new Migration1783691346AddAppCapabilityPermissions())->update($this->connection);

        static::assertContains(ContextGateway::PERMISSION, $this->requestedPrivileges($appId));
        static::assertNotContains(ContextGateway::PERMISSION, $this->grantedPrivileges($appId));
    }

    public function testTaxProviderCapabilityIsBackfilled(): void
    {
        $appId = $this->createApp(rolePrivileges: [], requestedPrivileges: []);
        $this->createTaxProvider($appId);

        (new Migration1783691346AddAppCapabilityPermissions())->update($this->connection);

        static::assertContains(Tax::PERMISSION, $this->grantedPrivileges($appId));
    }

    public function testMigrationIsIdempotent(): void
    {
        $appId = $this->createApp(rolePrivileges: [], requestedPrivileges: [], columns: [
            'checkout_gateway_url' => 'https://example.com/checkout',
        ]);

        $migration = new Migration1783691346AddAppCapabilityPermissions();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame([CheckoutGateway::PERMISSION], array_values($this->grantedPrivileges($appId)));
    }

    /**
     * @param list<string> $rolePrivileges
     * @param list<string> $requestedPrivileges
     * @param array<string, string> $columns
     */
    private function createApp(array $rolePrivileges, array $requestedPrivileges, array $columns = []): string
    {
        $aclRoleId = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'test-role-' . Uuid::randomHex(),
            'privileges' => json_encode($rolePrivileges, \JSON_THROW_ON_ERROR),
            'created_at' => '2020-01-01 00:00:00',
        ]);

        $appId = Uuid::randomHex();
        $this->connection->insert('app', array_merge([
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'TestApp' . $appId,
            'version' => '1.0.0',
            'integration_id' => Uuid::randomBytes(),
            'acl_role_id' => $aclRoleId,
            'requested_privileges' => json_encode($requestedPrivileges, \JSON_THROW_ON_ERROR),
            'created_at' => '2020-01-01 00:00:00',
        ], $columns));

        return $appId;
    }

    private function createTaxProvider(string $appHexId): void
    {
        $this->connection->insert('tax_provider', [
            'id' => Uuid::randomBytes(),
            'identifier' => 'test-tax-provider',
            'app_id' => Uuid::fromHexToBytes($appHexId),
            'process_url' => 'https://example.com/tax',
            'created_at' => '2020-01-01 00:00:00',
        ]);
    }

    /**
     * @return list<string>
     */
    private function grantedPrivileges(string $appHexId): array
    {
        $json = $this->connection->fetchOne(
            'SELECT r.privileges FROM acl_role r INNER JOIN app a ON a.acl_role_id = r.id WHERE a.id = :id',
            ['id' => Uuid::fromHexToBytes($appHexId)]
        );

        /** @var list<string> $privileges */
        $privileges = json_decode((string) $json ?: '[]', true, flags: \JSON_THROW_ON_ERROR);

        return $privileges;
    }

    /**
     * @return list<string>
     */
    private function requestedPrivileges(string $appHexId): array
    {
        $json = $this->connection->fetchOne(
            'SELECT requested_privileges FROM app WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($appHexId)]
        );

        /** @var list<string> $privileges */
        $privileges = json_decode((string) $json ?: '[]', true, flags: \JSON_THROW_ON_ERROR);

        return $privileges;
    }
}
