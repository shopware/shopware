<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1791274683ContentLayoutTranslatePrivilege;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1791274683ContentLayoutTranslatePrivilege::class)]
class Migration1791274683ContentLayoutTranslatePrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1791274683ContentLayoutTranslatePrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1791274683ContentLayoutTranslatePrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1791274683, $this->migration->getCreationTimestamp());
    }

    public function testGrantsTheTranslatePrivilegeToExperienceStudioEditorsOnce(): void
    {
        $roleId = $this->createRole(['experience_studio.editor']);

        // run twice to prove idempotency
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertSame(['experience_studio.editor', 'content_layout:translate'], $this->fetchPrivileges($roleId));
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        // no seeded role holds the editor privilege, so none of them may change
        $roleIds = [
            $this->createRole(['experience_studio.creator']),
            $this->createRole(['experience_studio.deleter']),
            $this->createRole(['experience_studio.viewer']),
            $this->createRole(['experience_studio.translator']),
        ];
        $before = $this->fetchRoles($roleIds);

        $this->migration->update($this->connection);

        static::assertSame($before, $this->fetchRoles($roleIds));
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test content layout translate acl migration',
            'privileges' => \json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    /**
     * @param list<string> $roleIds
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRoles(array $roleIds): array
    {
        $roles = [];

        foreach ($roleIds as $roleId) {
            $role = $this->connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $roleId]);
            static::assertIsArray($role);

            $roles[] = $role;
        }

        return $roles;
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
