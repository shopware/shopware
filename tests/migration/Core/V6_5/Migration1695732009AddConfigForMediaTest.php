<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1695732009AddConfigForMedia;

/**
 * @internal
 */
#[CoversClass(Migration1695732009AddConfigForMedia::class)]
class Migration1695732009AddConfigForMediaTest extends TestCase
{
    private Connection $connection;

    private Migration1695732009AddConfigForMedia $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1695732009AddConfigForMedia();
        $this->migration->update($this->connection);
        $this->connection->executeStatement('ALTER TABLE `media` DROP COLUMN `config`');
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'media', 'config'));
    }

    public function testUpdateDestructiveWillChangeNothing(): void
    {
        $this->migration->update($this->connection);
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'media', 'config'));

        $this->migration->updateDestructive($this->connection);
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'media', 'config'));
    }

    public function testUpdateWithMultipleRunsWillNotFail(): void
    {
        $this->migration->update($this->connection);
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'media', 'config'));
        $this->migration->update($this->connection);
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'media', 'config'));
    }
}
