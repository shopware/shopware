<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1701677136RemovePluginChangelogField;

/**
 * @internal
 */
#[CoversClass(Migration1701677136RemovePluginChangelogField::class)]
class Migration1701677136RemovePluginChangelogFieldTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1701677136, (new Migration1701677136RemovePluginChangelogField())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $this->addColumn();

        $migration = new Migration1701677136RemovePluginChangelogField();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'plugin_translation', 'changelog'));
    }

    private function addColumn(): void
    {
        $this->connection->executeStatement(
            'ALTER TABLE `plugin_translation` ADD COLUMN `changelog` JSON NOT NULL'
        );
    }
}
