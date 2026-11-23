<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRoles;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRoles::class)]
class Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRolesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRoles $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1794988092AddPaymentPrivilegesToShopwarePaymentsRoles();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794988092, $this->migration->getCreationTimestamp());
    }

    public function testAddsPaymentViewerKeyToShopwarePaymentsViewerRoles(): void
    {
        $roleId = $this->createRole(['sw-payments.viewer', 'payment_method:read']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('sw-payments.viewer', $privileges);
        static::assertCount(1, \array_keys($privileges, 'payment.viewer', true));
        static::assertNotContains('payment.editor', $privileges);
        static::assertNotContains('payment_method:update', $privileges);
    }

    public function testAddsPaymentEditorPrivilegesToShopwarePaymentsEditorRoles(): void
    {
        $roleId = $this->createRole(['sw-payments.viewer', 'sw-payments.editor', 'payment_method:read']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('sw-payments.editor', $privileges);
        static::assertCount(1, \array_keys($privileges, 'payment.viewer', true));
        static::assertCount(1, \array_keys($privileges, 'payment.editor', true));
        static::assertCount(1, \array_keys($privileges, 'payment_method:update', true));
        static::assertContains('media:create', $privileges);
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['product.viewer']);
        $before = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $after = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($before, $after);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test payments acl migration',
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
        $privileges = $this->connection->fetchOne(
            'SELECT `privileges` FROM `acl_role` WHERE id = :id',
            ['id' => $roleId]
        );

        static::assertIsString($privileges);

        $decodedPrivileges = \json_decode($privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decodedPrivileges);
        static::assertTrue(\array_is_list($decodedPrivileges));
        foreach ($decodedPrivileges as $decodedPrivilege) {
            static::assertIsString($decodedPrivilege);
        }

        /** @var list<string> $decodedPrivileges */
        return $decodedPrivileges;
    }
}
