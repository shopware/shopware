<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1787216476AddDocumentNumberTypeNameUniqueIndex;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787216476AddDocumentNumberTypeNameUniqueIndex::class)]
class Migration1787216476AddDocumentNumberTypeNameUniqueIndexTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $seededIds = [];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->dropIndex();
    }

    protected function tearDown(): void
    {
        $this->removeSeededDocuments();
        $this->dropIndex();

        parent::tearDown();
    }

    public function testUpdateAddsUniqueIndex(): void
    {
        static::assertFalse($this->indexExists());

        $migration = new Migration1787216476AddDocumentNumberTypeNameUniqueIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->indexExists());
    }

    public function testUpdateSkipsIndexWhenDuplicatesExist(): void
    {
        static::assertFalse($this->indexExists());

        $this->seedDocument();
        $this->seedDocument();

        (new Migration1787216476AddDocumentNumberTypeNameUniqueIndex())->update($this->connection);

        static::assertFalse($this->indexExists());
    }

    private function seedDocument(): void
    {
        $id = Uuid::randomBytes();

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->connection->insert('document', [
                'id' => $id,
                'document_type_id' => Uuid::randomBytes(),
                'order_id' => Uuid::randomBytes(),
                'order_version_id' => Uuid::randomBytes(),
                'type_name' => 'invoice',
                'config' => \json_encode(['documentNumber' => 'INV-1'], \JSON_THROW_ON_ERROR),
                'deep_link_code' => Uuid::randomHex(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]);
        } finally {
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->seededIds[] = $id;
    }

    private function removeSeededDocuments(): void
    {
        foreach ($this->seededIds as $id) {
            $this->connection->delete('document', ['id' => $id]);
        }

        $this->seededIds = [];
    }

    private function indexExists(): bool
    {
        return $this->connection->fetchOne(
            'SHOW INDEX FROM `document` WHERE `Key_name` = :name',
            ['name' => Migration1787216476AddDocumentNumberTypeNameUniqueIndex::INDEX_NAME],
        ) !== false;
    }

    private function dropIndex(): void
    {
        if ($this->indexExists()) {
            $this->connection->executeStatement(\sprintf(
                'ALTER TABLE `document` DROP INDEX `%s`',
                Migration1787216476AddDocumentNumberTypeNameUniqueIndex::INDEX_NAME
            ));
        }
    }
}
