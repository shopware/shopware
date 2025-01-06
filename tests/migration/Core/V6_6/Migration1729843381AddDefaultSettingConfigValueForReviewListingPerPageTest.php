<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1729843381AddDefaultSettingConfigValueForReviewListingPerPage;

/**
 * @internal
 */
#[CoversClass(Migration1729843381AddDefaultSettingConfigValueForReviewListingPerPage::class)]
class Migration1729843381AddDefaultSettingConfigValueForReviewListingPerPageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private const CONFIG_KEY = 'core.listing.reviewsPerPage';

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1729843381AddDefaultSettingConfigValueForReviewListingPerPage();
        static::assertSame(1729843381, $migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $connection = self::getContainer()->get(Connection::class);

        $connection->delete('system_config', [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration = new Migration1729843381AddDefaultSettingConfigValueForReviewListingPerPage();

        $migration->update($connection);
        static::assertSame(10, $this->getConfigValue($connection));

        $connection->update('system_config', [
            'configuration_value' => json_encode(['_value' => 8], \JSON_THROW_ON_ERROR),
        ], [
            'configuration_key' => self::CONFIG_KEY,
        ]);

        $migration->update($connection);
        static::assertSame(8, $this->getConfigValue($connection));
    }

    private function getConfigValue(Connection $connection): int
    {
        $configValue = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => self::CONFIG_KEY]
        );
        static::assertIsString($configValue);
        $configValue = json_decode($configValue, true, 512, \JSON_THROW_ON_ERROR);

        return (int) $configValue['_value'];
    }
}
