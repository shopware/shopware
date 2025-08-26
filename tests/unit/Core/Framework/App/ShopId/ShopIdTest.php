<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-import-type ShopIdV1Config from ShopId
 * @phpstan-import-type ShopIdV2Config from ShopId
 */
#[CoversClass(ShopId::class)]
#[Package('framework')]
class ShopIdTest extends TestCase
{
    public function testCreatesShopIdFromValidV1Config(): void
    {
        $config = ['value' => '123456789', 'app_url' => 'https://foo.bar'];
        $shopId = ShopId::fromSystemConfig($config);

        static::assertSame($shopId->id, $config['value']);
        static::assertSame(1, $shopId->version);
        static::assertSame(['app_url' => $config['app_url']], $shopId->fingerprints);
    }

    public function testCreatesShopIdFromValidV2Config(): void
    {
        $config = ['id' => '123456789', 'version' => 2, 'fingerprints' => [
            'sales_channel_domain_urls' => 'SALES_CHANNEL_DOMAIN_URLS',
            'app_url' => 'APP_URL',
        ]];
        $shopId = ShopId::fromSystemConfig($config);

        static::assertSame($shopId->id, $config['id']);
        static::assertSame(2, $shopId->version);
        static::assertSame($config['fingerprints'], $shopId->fingerprints);
    }

    public function testThrowsIfSystemConfigIsInvalid(): void
    {
        static::expectException(AppException::class);
        static::expectExceptionMessage('The configuration values for "core.app.shopIdV2" and "core.app.shopId" in the system config are invalid.');

        ShopId::fromSystemConfig(['foo' => 'bar']);
    }

    public function testPutsFingerprintsOn(): void
    {
        $fingerprints = [
            'sales_channel_domain_urls' => 'SALES_CHANNEL_DOMAIN_URLS',
            'app_url' => 'APP_URL',
        ];

        $shopId = ShopId::v2('123456789', $fingerprints);

        static::assertSame($fingerprints['sales_channel_domain_urls'], $shopId->getFingerprint('sales_channel_domain_urls'));
        static::assertSame($fingerprints['app_url'], $shopId->getFingerprint('app_url'));
        static::assertNull($shopId->getFingerprint('installation_path'));
    }
}
