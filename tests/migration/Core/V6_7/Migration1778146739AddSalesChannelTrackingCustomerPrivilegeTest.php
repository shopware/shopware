<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1778146739AddSalesChannelTrackingCustomerPrivilege;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1778146739AddSalesChannelTrackingCustomerPrivilege::class)]
class Migration1778146739AddSalesChannelTrackingCustomerPrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1778146739AddSalesChannelTrackingCustomerPrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1778146739AddSalesChannelTrackingCustomerPrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1778146739, $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->prepareTestData();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->connection->fetchOne(
            'SELECT privileges FROM acl_role WHERE name = :name',
            ['name' => 'Customer Viewer']
        );

        static::assertNotFalse($privileges);
        $decodedPrivileges = json_decode($privileges, true);

        static::assertContains(
            'sales_channel_tracking_customer:read',
            $decodedPrivileges
        );
    }

    private function prepareTestData(): void
    {
        $this->connection->insert('acl_role', [
            'id' => Uuid::randomBytes(),
            'name' => 'Customer Viewer',
            'privileges' => json_encode(['customer.viewer']),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}
