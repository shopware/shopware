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
use Shopware\Core\Migration\V6_7\Migration1793952793LandingPageLayoutAssignmentPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1793952793LandingPageLayoutAssignmentPrivileges::class)]
class Migration1793952793LandingPageLayoutAssignmentPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1793952793LandingPageLayoutAssignmentPrivileges $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1793952793LandingPageLayoutAssignmentPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1793952793, $this->migration->getCreationTimestamp());
    }

    /**
     * @param list<string> $additionalPrivileges
     */
    #[DataProvider('studioRoleProvider')]
    public function testAddsLandingPagePrivilegesIdempotently(string $role, array $additionalPrivileges): void
    {
        $roleId = $this->createRole([$role, 'custom:read']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains($role, $privileges);
        static::assertContains('custom:read', $privileges);
        foreach ($additionalPrivileges as $privilege) {
            static::assertContains($privilege, $privileges);
            static::assertCount(1, \array_keys($privileges, $privilege, true));
        }
        if ($role === 'experience_studio.viewer') {
            static::assertNotContains('landing_page_content_layout:create', $privileges);
            static::assertNotContains('landing_page_content_layout:update', $privileges);
            static::assertNotContains('landing_page_content_layout:delete', $privileges);
        }
    }

    /**
     * @return \Generator<string, array{string, list<string>}>
     */
    public static function studioRoleProvider(): \Generator
    {
        yield 'viewers can select landing pages' => ['experience_studio.viewer', ['landing_page:read']];
        yield 'editors can create move and remove assignments' => ['experience_studio.editor', [
            'landing_page_content_layout:create',
            'landing_page_content_layout:update',
            'landing_page_content_layout:delete',
        ]];
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['customer.viewer']);
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
            'name' => 'test landing page layout acl migration',
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
