<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `app_template` DROP COLUMN `hash`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testMigration(): void
    {
        $migration = new Migration1733323215AddHashToAppTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns('app_template');

        static::assertArrayHasKey('hash', $columns);
    }
}
