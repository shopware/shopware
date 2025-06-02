<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\ColumnExistsTrait;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1696515133AddCheckoutGatewayUrl;

/**
 * @internal
 */
#[CoversClass(Migration1696515133AddCheckoutGatewayUrl::class)]
#[Package('framework')]
class Migration1743151679AddContextGatewayUrlTest extends TestCase
{
    use ColumnExistsTrait;
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('ALTER TABLE `app` DROP COLUMN `checkout_gateway_url`');

        $migration = new Migration1696515133AddCheckoutGatewayUrl();

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'app', 'checkout_gateway_url'));
    }
}
