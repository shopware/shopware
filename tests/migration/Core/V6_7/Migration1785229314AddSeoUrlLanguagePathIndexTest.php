<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1785229314AddSeoUrlLanguagePathIndex;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1785229314AddSeoUrlLanguagePathIndex::class)]
class Migration1785229314AddSeoUrlLanguagePathIndexTest extends TestCase
{
    private const INDEX_NAME = 'idx.seo_url.language_path';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1785229314, (new Migration1785229314AddSeoUrlLanguagePathIndex())->getCreationTimestamp());
    }

    public function testMigrationCreatesExpectedIndexAndIsIdempotent(): void
    {
        $this->rollback();

        $migration = new Migration1785229314AddSeoUrlLanguagePathIndex();
        $migration->update($this->connection);
        // second run must be a no-op (idempotent)
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'seo_url', self::INDEX_NAME));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'seo_url', self::INDEX_NAME, ['language_id', 'seo_path_info']));
    }

    private function rollback(): void
    {
        if (!TableHelper::indexExists($this->connection, 'seo_url', self::INDEX_NAME)) {
            return;
        }

        $this->connection->executeStatement('DROP INDEX `' . self::INDEX_NAME . '` ON `seo_url`');
    }
}
