<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1781703825AddSalesChannelFileAclPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1781703825AddSalesChannelFileAclPrivileges::class)]
class Migration1781703825AddSalesChannelFileAclPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1781703825AddSalesChannelFileAclPrivileges $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1781703825AddSalesChannelFileAclPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1781703825, $this->migration->getCreationTimestamp());
    }

    public function testAddsSalesChannelFilePrivilegesToSalesChannelRoles(): void
    {
        $viewerRoleId = $this->createRole('viewer', ['sales_channel.viewer']);
        $editorRoleId = $this->createRole('editor', ['sales_channel.editor']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $viewerPrivileges = $this->fetchPrivileges($viewerRoleId);
        static::assertContains('sales_channel.viewer', $viewerPrivileges);
        static::assertContains('sales_channel_file:read', $viewerPrivileges);
        static::assertCount(1, \array_keys($viewerPrivileges, 'sales_channel_file:read', true));

        $editorPrivileges = $this->fetchPrivileges($editorRoleId);
        static::assertContains('sales_channel.editor', $editorPrivileges);
        static::assertContains('sales_channel_file:create', $editorPrivileges);
        static::assertContains('sales_channel_file:update', $editorPrivileges);
        static::assertCount(1, \array_keys($editorPrivileges, 'sales_channel_file:create', true));
        static::assertCount(1, \array_keys($editorPrivileges, 'sales_channel_file:update', true));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole('unrelated', ['category.viewer']);
        $before = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        $this->migration->update($this->connection);

        $after = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);

        static::assertSame($before, $after);
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(string $name, array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test sales channel file acl migration ' . $name,
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
