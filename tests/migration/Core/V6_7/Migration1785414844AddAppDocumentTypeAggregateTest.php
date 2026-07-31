<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1785414844AddAppDocumentTypeAggregate;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1785414844AddAppDocumentTypeAggregate::class)]
class Migration1785414844AddAppDocumentTypeAggregateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_document_type_translation`;');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_document_type`;');
    }

    public function testMigration(): void
    {
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_document_type'));
        static::assertFalse(TableHelper::tableExists($this->connection, 'app_document_type_translation'));

        $migration = new Migration1785414844AddAppDocumentTypeAggregate();
        static::assertSame(1785414844, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'app_document_type'));
        static::assertTrue(TableHelper::tableExists($this->connection, 'app_document_type_translation'));

        static::assertCount(7, TableHelper::getTable($this->connection, 'app_document_type')->columns);
        static::assertCount(5, TableHelper::getTable($this->connection, 'app_document_type_translation')->columns);
    }
}
