<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1643366069AddSeoUrlUpdaterIndex;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1643366069AddSeoUrlUpdaterIndex
 */
class Migration1643366069AddSeoUrlUpdaterIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testKeyExistsDoesNothing(): void
    {
        static::assertCount(2, $this->connection->fetchAllAssociative('SHOW INDEX FROM seo_url WHERE Key_name = "idx.delete_query"'));

        $m = new Migration1643366069AddSeoUrlUpdaterIndex();
        $m->update($this->connection);

        static::assertCount(2, $this->connection->fetchAllAssociative('SHOW INDEX FROM seo_url WHERE Key_name = "idx.delete_query"'));
    }

    public function testMissingIndexAdded(): void
    {
        $this->connection->executeStatement('DROP INDEX `idx.delete_query` ON seo_url;');

        static::assertCount(0, $this->connection->fetchAllAssociative('SHOW INDEX FROM seo_url WHERE Key_name = "idx.delete_query"'));

        $m = new Migration1643366069AddSeoUrlUpdaterIndex();
        $m->update($this->connection);

        static::assertCount(2, $this->connection->fetchAllAssociative('SHOW INDEX FROM seo_url WHERE Key_name = "idx.delete_query"'));
    }
}
