<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_6\Migration1696515133AddCheckoutGatewayUrl;
use Shopware\Core\Migration\V6_7\Migration1743151679AddContextGatewayUrl;

/**
 * @internal
 */
#[CoversClass(Migration1696515133AddCheckoutGatewayUrl::class)]
#[Package('framework')]
class Migration1743151679AddContextGatewayUrlTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1743151679, (new Migration1743151679AddContextGatewayUrl())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement('ALTER TABLE `app` DROP COLUMN `checkout_gateway_url`');

        $migration = new Migration1696515133AddCheckoutGatewayUrl();

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(TableHelper::columnExists($connection, 'app', 'checkout_gateway_url'));
    }
}
