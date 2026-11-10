<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794221136AddProductDocumentMediaUsagePrivileges;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1794221136AddProductDocumentMediaUsagePrivileges::class)]
class Migration1794221136AddProductDocumentMediaUsagePrivilegesTest extends TestCase
{
    private Connection $connection;

    private Migration1794221136AddProductDocumentMediaUsagePrivileges $migration;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE acl_role (id BLOB PRIMARY KEY, name TEXT, privileges TEXT, created_at TEXT, updated_at TEXT)');
        $this->migration = new Migration1794221136AddProductDocumentMediaUsagePrivileges();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794221136, $this->migration->getCreationTimestamp());
    }

    public function testMediaViewersCanReadProductDocumentsAndTheirTranslations(): void
    {
        $roleId = $this->createRole(['media.viewer']);

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $privileges = $this->fetchPrivileges($roleId);
        static::assertSame(['media.viewer', 'product_document:read', 'product_document_translation:read'], $privileges);
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
            'name' => 'product document media usage privileges test',
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
