<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopId;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(ShopIdProvider::class)]
class ShopIdProviderTest extends TestCase
{
    public function testGetShopIdWillCreateOneIfNoneIsGiven(): void
    {
        $systemConfigService = new StaticSystemConfigService();
        $eventDispatcher = new CollectingEventDispatcher();

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([]),
            $eventDispatcher
        );

        $shopId = $shopIdProvider->getShopId();

        static::assertSame(16, \strlen($shopId));

        $systemConfigValue = $systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY);

        static::assertIsArray($systemConfigValue);
        static::assertEquals([
            'value' => $shopId,
            'app_url' => EnvironmentHelper::getVariable('APP_URL'),
        ], $systemConfigValue);

        $events = $eventDispatcher->getEvents();

        static::assertCount(1, $events);

        $shopIdChangedEvent = $events[0];
        static::assertInstanceOf(ShopIdChangedEvent::class, $shopIdChangedEvent);

        static::assertSame($shopId, $shopIdChangedEvent->newShopId['value']);
        static::assertSame(EnvironmentHelper::getVariable('APP_URL'), $shopIdChangedEvent->newShopId['app_url']);
        static::assertNull($shopIdChangedEvent->oldShopId);
    }

    public function testGetShopIdWillNotCreateNewIfAlreadyGiven(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                'app_url' => EnvironmentHelper::getVariable('APP_URL'),
                'value' => '1234567890',
            ],
        ]);

        $eventDispatcher = new CollectingEventDispatcher();

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([]),
            $eventDispatcher
        );

        $shopId = $shopIdProvider->getShopId();

        static::assertSame('1234567890', $shopId);
        static::assertCount(0, $eventDispatcher->getEvents());
    }

    public function testItThrowsAppUrlChangedExceptionIfAppsAreInstalled(): void
    {
        $newAppUrl = EnvironmentHelper::getVariable('APP_URL');
        $oldAppUrl = $newAppUrl . 'foo';

        $systemConfigService = new StaticSystemConfigService([
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                'value' => '1234567890',
                'app_url' => $oldAppUrl,
            ],
        ]);
        $appRepository = new StaticEntityRepository([
            new IdSearchResult(
                1,
                [
                    [
                        'primaryKey' => '123',
                        'data' => [],
                    ],
                ],
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $appRepository,
            new CollectingEventDispatcher()
        );

        static::expectException(AppUrlChangeDetectedException::class);
        $shopIdProvider->getShopId();
    }

    public function testItWillUpdateTheAppUrlIfNoAppsAreInstalledAndTheUrlChanged(): void
    {
        $newAppUrl = EnvironmentHelper::getVariable('APP_URL');
        $oldAppUrl = $newAppUrl . 'foo';
        $shopId = '1234567890';

        $systemConfigService = new StaticSystemConfigService([
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                'app_url' => $oldAppUrl,
                'value' => $shopId,
            ],
        ]);

        $appRepository = new StaticEntityRepository([
            new IdSearchResult(
                0,
                [[
                    'data' => [],
                ]],
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $eventDispatcher = new CollectingEventDispatcher();

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            $appRepository,
            $eventDispatcher
        );

        $result = $shopIdProvider->getShopId();
        static::assertSame($shopId, $result);
        static::assertEquals([
            'value' => $result,
            'app_url' => $newAppUrl,
        ], $systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);

        $shopIdChangedEvent = $events[0];
        static::assertInstanceOf(ShopIdChangedEvent::class, $shopIdChangedEvent);

        static::assertSame($shopId, $shopIdChangedEvent->newShopId['value']);
        static::assertSame($newAppUrl, $shopIdChangedEvent->newShopId['app_url']);

        $oldConfigValue = $shopIdChangedEvent->oldShopId;
        static::assertNotNull($oldConfigValue);
        static::assertSame($shopId, $oldConfigValue['value']);
        static::assertSame($oldAppUrl, $oldConfigValue['app_url']);
    }

    public function testDeleteShopId(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY => [
                'value' => '123456789',
                'app_url' => 'http://someShop',
            ],
        ]);

        $eventDispatcher = new CollectingEventDispatcher();

        $shopIdProvider = new ShopIdProvider(
            $systemConfigService,
            new StaticEntityRepository([]),
            $eventDispatcher
        );

        static::assertNotNull($systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));

        $shopIdProvider->deleteShopId();

        static::assertNull($systemConfigService->get(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY));

        $events = $eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(ShopIdDeletedEvent::class, $events[0]);
    }
}
