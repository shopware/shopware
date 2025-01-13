<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1736319636AddTableDocumentMedia;

/**
 * @internal
 */
#[CoversClass(Migration1736319636AddTableDocumentMedia::class)]
class Migration1736319636AddTableDocumentMediaTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        try {
            $this->connection->executeStatement(
                'DROP TABLE IF EXISTS `document_media`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        $migration = new Migration1736319636AddTableDocumentMedia();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns('document_media');

        static::assertArrayHasKey('id', $columns);
        static::assertArrayHasKey('document_id', $columns);
        static::assertArrayHasKey('media_id', $columns);
        static::assertArrayHasKey('file_extension', $columns);
        static::assertArrayHasKey('custom_fields', $columns);
        static::assertArrayHasKey('created_at', $columns);
        static::assertArrayHasKey('updated_at', $columns);
    }
}
