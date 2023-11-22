<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(ShopIdProvider::class)]
class ShopIdProviderTest extends TestCase
{
    public function testGetShopIdWillCreateOneIfNoneIsGiven(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn(null);

        $systemConfigService->expects(static::once())
            ->method('set')
            ->with(static::callback(function ($key) {
                return $key === ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY;
            }), static::callback(function ($value) {
                static::assertArrayHasKey('app_url', $value);
                static::assertArrayHasKey('value', $value);

                static::assertSame(16, \strlen($value['value']));

                return true;
            }));

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
        );

        $shopId = $shopIdProvider->getShopId();

        static::assertSame(16, \strlen($shopId));
    }

    public function testGetShopIdWillNotCreateNewIfAlreadyGiven(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn([
                'app_url' => EnvironmentHelper::getVariable('APP_URL'),
                'value' => '1234567890',
            ]);

        $systemConfigService->expects(static::never())
            ->method('set');

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
        );

        $shopId = $shopIdProvider->getShopId();

        static::assertSame('1234567890', $shopId);
    }

    public function testItThrowsAppUrlChangedExceptionIfAppsAreInstalled(): void
    {
        $newAppUrl = EnvironmentHelper::getVariable('APP_URL');
        $oldAppUrl = $newAppUrl . 'foo';

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn([
                'app_url' => $oldAppUrl,
                'value' => '1234567890',
            ]);

        $idSearchResult = new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => '123',
                    'data' => [],
                ],
            ],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->expects(static::once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $appRepository,
        );

        static::expectException(AppUrlChangeDetectedException::class);
        $shopIdProvider->getShopId();
    }

    public function testItWillUpdateTheAppUrlIfNoAppsAreInstalledAndTheUrlChanged(): void
    {
        $newAppUrl = EnvironmentHelper::getVariable('APP_URL');
        $oldAppUrl = $newAppUrl . 'foo';
        $shopId = '1234567890';

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects(static::once())
            ->method('get')
            ->with(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY)
            ->willReturn([
                'app_url' => $oldAppUrl,
                'value' => $shopId,
            ]);
        $systemConfigService->expects(static::once())
            ->method('set')
            ->with(
                ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY,
                static::callback(function (array $data) use ($newAppUrl, $shopId) {
                    static::assertArrayHasKey('app_url', $data);
                    static::assertSame($newAppUrl, $data['app_url']);

                    static::assertArrayHasKey('value', $data);
                    static::assertSame($shopId, $data['value']);

                    return true;
                }),
            );

        $idSearchResult = new IdSearchResult(
            0,
            [[
                'data' => [],
            ]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $appRepository = $this->createMock(EntityRepository::class);
        $appRepository->expects(static::once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $appRepository,
        );

        $result = $shopIdProvider->getShopId();
        static::assertSame($shopId, $result);
    }
}
