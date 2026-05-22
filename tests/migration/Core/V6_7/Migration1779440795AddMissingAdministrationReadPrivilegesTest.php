<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1779440795AddMissingAdministrationReadPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779440795AddMissingAdministrationReadPrivileges::class)]
class Migration1779440795AddMissingAdministrationReadPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1779440795AddMissingAdministrationReadPrivileges $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1779440795AddMissingAdministrationReadPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779440795, $this->migration->getCreationTimestamp());
    }

    public function testAddsMissingReadPrivilegesToAdministrationViewerRoles(): void
    {
        $roleIdsByPrivilege = [];

        foreach (Migration1779440795AddMissingAdministrationReadPrivileges::NEW_PRIVILEGES as $rolePrivilege => $additionalPrivileges) {
            $roleIdsByPrivilege[$rolePrivilege] = $this->createRole($rolePrivilege, [$rolePrivilege]);
        }

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        foreach (Migration1779440795AddMissingAdministrationReadPrivileges::NEW_PRIVILEGES as $rolePrivilege => $additionalPrivileges) {
            $privileges = $this->fetchPrivileges($roleIdsByPrivilege[$rolePrivilege]);

            static::assertContains($rolePrivilege, $privileges);

            foreach ($additionalPrivileges as $additionalPrivilege) {
                static::assertContains($additionalPrivilege, $privileges);
                static::assertCount(1, \array_keys($privileges, $additionalPrivilege, true));
            }
        }
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole('unrelated role', ['category.viewer']);
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
            'name' => 'test missing admin acl privileges ' . $name,
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
