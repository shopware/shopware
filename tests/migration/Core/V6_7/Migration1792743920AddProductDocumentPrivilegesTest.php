<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792743920AddProductDocumentPrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1792743920AddProductDocumentPrivileges::class)]
class Migration1792743920AddProductDocumentPrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1792743920AddProductDocumentPrivileges $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1792743920AddProductDocumentPrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1792743920, $this->migration->getCreationTimestamp());
    }

    public function testProductViewersCanReadDocuments(): void
    {
        $roleId = $this->createRole(['product.viewer']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);
        static::assertSame(['product.viewer', 'product_document:read'], $privileges);
    }

    public function testProductEditorsCanMaintainDocuments(): void
    {
        $roleId = $this->createRole(['product.viewer', 'product.editor']);

        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);
        foreach (['product_document:read', 'product_document:create', 'product_document:update', 'product_document:delete'] as $privilege) {
            static::assertContains($privilege, $privileges);
        }
    }

    public function testOtherRolesAreLeftAlone(): void
    {
        $roleId = $this->createRole(['category.viewer']);

        $this->migration->update($this->connection);

        static::assertSame(['category.viewer'], $this->fetchPrivileges($roleId));
    }

    /**
     * @param list<string> $privileges
     */
    private function createRole(array $privileges): string
    {
        $roleId = Uuid::randomBytes();

        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'product document privileges test',
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }

    /**
     * @return list<string>
     */
    private function fetchPrivileges(string $roleId): array
    {
        $privileges = $this->connection->fetchOne('SELECT `privileges` FROM `acl_role` WHERE `id` = :id', ['id' => $roleId]);
        static::assertIsString($privileges);

        /** @var list<string> $decoded */
        $decoded = json_decode($privileges, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
