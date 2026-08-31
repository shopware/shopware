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
use Shopware\Core\Migration\V6_7\Migration1787205808AddNumberRangeReadAclPrivilege;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1787205808AddNumberRangeReadAclPrivilege::class)]
class Migration1787205808AddNumberRangeReadAclPrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1787205808AddNumberRangeReadAclPrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1787205808AddNumberRangeReadAclPrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1787205808, $this->migration->getCreationTimestamp());
    }

    #[DataProvider('reservingRoleProvider')]
    public function testAddsNumberRangeReadToReservingRoles(string $reservingRole): void
    {
        $roleId = $this->createRole([$reservingRole]);

        // run twice to prove idempotency
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains($reservingRole, $privileges);
        static::assertContains('number_range:read', $privileges);
        static::assertCount(1, \array_keys($privileges, 'number_range:read', true));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function reservingRoleProvider(): \Generator
    {
        yield 'order editors reserve document numbers' => ['order.editor'];
        yield 'customer creators reserve customer numbers' => ['customer.creator'];
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
            'name' => 'test number range acl migration',
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
