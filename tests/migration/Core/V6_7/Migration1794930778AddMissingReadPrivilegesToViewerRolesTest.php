<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794930778AddMissingReadPrivilegesToViewerRoles;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1794930778AddMissingReadPrivilegesToViewerRoles::class)]
class Migration1794930778AddMissingReadPrivilegesToViewerRolesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1794930778AddMissingReadPrivilegesToViewerRoles $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1794930778AddMissingReadPrivilegesToViewerRoles();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794930778, $this->migration->getCreationTimestamp());
    }

    #[DataProvider('viewerRoleProvider')]
    public function testAddsMissingReadToViewerRole(string $roleKey, string $expectedPrivilege): void
    {
        $roleId = $this->createRole([$roleKey]);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains($roleKey, $privileges);
        static::assertCount(1, \array_keys($privileges, $expectedPrivilege, true));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function viewerRoleProvider(): iterable
    {
        yield 'category viewer' => ['category.viewer', 'sales_channel_domain:read'];
        yield 'cms viewer' => ['cms.viewer', 'cms_slot_translation:read'];
        yield 'landing page viewer' => ['landing_page.viewer', 'cms_slot_translation:read'];
        yield 'theme viewer' => ['theme.viewer', 'sales_channel_type:read'];
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
            'name' => 'test viewer acl migration',
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
