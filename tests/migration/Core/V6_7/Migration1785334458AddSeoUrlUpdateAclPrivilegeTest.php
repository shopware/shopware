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
use Shopware\Core\Migration\V6_7\Migration1785334458AddSeoUrlUpdateAclPrivilege;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1785334458AddSeoUrlUpdateAclPrivilege::class)]
class Migration1785334458AddSeoUrlUpdateAclPrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1785334458AddSeoUrlUpdateAclPrivilege $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1785334458AddSeoUrlUpdateAclPrivilege();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1785334458, $this->migration->getCreationTimestamp());
    }

    #[DataProvider('editorRoleProvider')]
    public function testAddsSeoUrlUpdateToEditorRoles(string $editorRole): void
    {
        $roleId = $this->createRole([$editorRole]);

        // run twice to prove idempotency
        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains($editorRole, $privileges);
        static::assertContains('seo_url:update', $privileges);
        static::assertCount(1, \array_keys($privileges, 'seo_url:update', true));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function editorRoleProvider(): \Generator
    {
        yield 'product editors save canonical urls' => ['product.editor'];
        yield 'category editors save canonical urls' => ['category.editor'];
        yield 'landing page editors save canonical urls' => ['landing_page.editor'];
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
            'name' => 'test seo url acl migration',
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
