<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1696515133AddCheckoutGatewayUrl;

/**
 * @internal
 */
#[CoversClass(Migration1696515133AddCheckoutGatewayUrl::class)]
#[Package('checkout')]
class Migration1696515133AddCheckoutGatewayUrlTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns('app');

        static::assertArrayHasKey('checkout_gateway_url', $columns);
        static::assertFalse($columns['checkout_gateway_url']->getNotnull());
    }

    private function migrate(): void
    {
        (new Migration1696515133AddCheckoutGatewayUrl())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `app` DROP COLUMN `checkout_gateway_url`');
    }
}
