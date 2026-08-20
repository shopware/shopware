<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1787216476AddDocumentNumberTypeNameUniqueIndex;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1787216476AddDocumentNumberTypeNameUniqueIndex::class)]
class Migration1787216476AddDocumentNumberTypeNameUniqueIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->dropIndex();
    }

    protected function tearDown(): void
    {
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
