<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1733323215AddHashToAppTemplate;

/**
 * @internal
 */
#[CoversClass(Migration1733323215AddHashToAppTemplate::class)]
#[Package('checkout')]
class Migration1733323215AddHashToAppTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1733323215, (new Migration1733323215AddHashToAppTemplate())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->dropHashColumn();

        $migration = new Migration1733323215AddHashToAppTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::columnExists($this->connection, 'app_template', 'hash'));
    }

    private function dropHashColumn(): void
    {
        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app_template` DROP COLUMN `hash`;'
            );
        } catch (\Throwable) {
        }
    }
}
