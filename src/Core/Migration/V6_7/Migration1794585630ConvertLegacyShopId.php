<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794585630ConvertLegacyShopId extends MigrationStep
{
    private const LEGACY_SHOP_ID_KEY = 'core.app.shopId';
    private const SHOP_ID_KEY = 'core.app.shopIdV2';

    public function getCreationTimestamp(): int
    {
        return 1794585630;
    }

    public function update(Connection $connection): void
    {
        if ($this->hasShopId($connection)) {
            return;
        }

        $legacyShopId = $this->fetchConfigValue($connection, self::LEGACY_SHOP_ID_KEY);
        if (!\is_string($legacyShopId['value'] ?? null) || !\is_string($legacyShopId['app_url'] ?? null)) {
            return;
        }

        $this->storeShopId($connection, [
            'id' => $legacyShopId['value'],
            'fingerprints' => [
                'app_url' => $legacyShopId['app_url'],
                'installation_path' => $this->getProjectDir(),
                'sales_channel_domain_urls' => $this->hashSalesChannelDomainUrls($connection),
            ],
            'version' => 2,
        ]);
    }

    private function hasShopId(Connection $connection): bool
    {
        return $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = ? AND `sales_channel_id` IS NULL',
            [self::SHOP_ID_KEY]
        ) !== false;
    }

    /**
     * @return array<mixed>|null
     */
    private function fetchConfigValue(Connection $connection, string $key): ?array
    {
        $value = $connection->fetchOne(
            'SELECT `configuration_value` FROM `system_config` WHERE `configuration_key` = ? AND `sales_channel_id` IS NULL',
            [$key]
        );

        if (!\is_string($value)) {
            return null;
        }

        $value = json_decode($value, true);

        return \is_array($value) && \is_array($value['_value'] ?? null) ? $value['_value'] : null;
    }

    /**
     * @param array<string, mixed> $shopId
     */
    private function storeShopId(Connection $connection, array $shopId): void
    {
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::SHOP_ID_KEY,
            'configuration_value' => json_encode(['_value' => $shopId], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getProjectDir(): string
    {
        $projectDir = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? null;
        if (\is_string($projectDir) && $projectDir !== '') {
            return $projectDir;
        }

        $dir = __DIR__;
        while (!is_dir($dir . '/vendor')) {
            if ($dir === \dirname($dir)) {
                return __DIR__;
            }
            $dir = \dirname($dir);
        }

        return $dir;
    }

    private function hashSalesChannelDomainUrls(Connection $connection): string
    {
        $urls = $connection->fetchFirstColumn('SELECT url FROM sales_channel_domain');

        // @phpstan-ignore-next-line shopware.hasher
        return \hash('md5', implode('', $urls));
    }
}
