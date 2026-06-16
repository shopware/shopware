<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779977359RemoveMailTemplateTypeTemplateDataColumn;

/**
 * @internal
 */
#[CoversClass(Migration1779977359RemoveMailTemplateTypeTemplateDataColumn::class)]
class Migration1779977359RemoveMailTemplateTypeTemplateDataColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779977359, (new Migration1779977359RemoveMailTemplateTypeTemplateDataColumn())->getCreationTimestamp());
    }

    public function testUpdateDestructiveRemovesTemplateDataColumn(): void
    {
        $this->ensureTemplateDataColumnExists();

        $migration = new Migration1779977359RemoveMailTemplateTypeTemplateDataColumn();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'mail_template_type', 'template_data'));
    }

    private function ensureTemplateDataColumnExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'mail_template_type', 'template_data')) {
            return;
        }

        $this->connection->executeStatement('ALTER TABLE `mail_template_type` ADD COLUMN `template_data` LONGTEXT COLLATE utf8mb4_unicode_ci NULL');
    }
}
