<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1788182786AddDefaultCurrenciesToSalesChannels;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1788182786AddDefaultCurrenciesToSalesChannels::class)]
class Migration1788182786AddDefaultCurrenciesToSalesChannelsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788182786, (new Migration1788182786AddDefaultCurrenciesToSalesChannels())->getCreationTimestamp());
    }

    public function testMigrationAssignsMissingDefaultCurrency(): void
    {
        $salesChannelId = Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL);
        $currencyId = $this->connection->fetchOne(
            'SELECT `currency_id` FROM `sales_channel` WHERE `id` = :id',
            ['id' => $salesChannelId]
        );
        static::assertIsString($currencyId);

        $this->connection->delete('sales_channel_currency', [
            'sales_channel_id' => $salesChannelId,
            'currency_id' => $currencyId,
        ]);

        $migration = new Migration1788182786AddDefaultCurrenciesToSalesChannels();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM `sales_channel_currency` WHERE `sales_channel_id` = :salesChannelId AND `currency_id` = :currencyId',
            [
                'salesChannelId' => $salesChannelId,
                'currencyId' => $currencyId,
            ]
        ));
    }
}
