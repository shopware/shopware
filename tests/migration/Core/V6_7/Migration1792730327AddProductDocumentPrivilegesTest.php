<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792730327AddProductDocumentPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792730327AddProductDocumentPrivileges::class)]
class Migration1792730327AddProductDocumentPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1792730327AddProductDocumentPrivileges $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1792730327AddProductDocumentPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792730327, $this->migration->getCreationTimestamp());
    }

    public function testProductViewersCanReadDocumentsAndTheirTranslations(): void
    {
        $roleId = $this->createRole(['product.viewer']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        static::assertContains('product_document:read', $privileges);
        static::assertContains('product_document_translation:read', $privileges);
        static::assertNotContains('product_document:update', $privileges);
        static::assertCount(1, \array_keys($privileges, 'product_document:read', true));
    }

    public function testProductEditorsCanMaintainDocuments(): void
    {
        $roleId = $this->createRole(['product.viewer', 'product.editor']);

        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);

        foreach (['product_document:create', 'product_document:update', 'product_document:delete'] as $privilege) {
            static::assertContains($privilege, $privileges);
        }
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $roleId = $this->createRole(['category.viewer']);
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
            'name' => 'test product document acl migration',
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
