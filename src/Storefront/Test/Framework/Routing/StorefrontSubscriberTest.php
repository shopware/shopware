<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class StorefrontSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testItAddsShopIdParam(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss');

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayHasKey('appShopId', $event->getParameters());
    }

    public function testItDoesNotAddShopIdParamWhenAppIsInactive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss', false);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayNotHasKey('swagShopId', $event->getParameters());
        static::assertArrayNotHasKey('appShopId', $event->getParameters());
    }

    public function testItDoesNotAddShopIdParamWhenAppUrlChanged(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/noThemeNoCss');

        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, [
            'app_url' => 'https://test.com',
            'value' => Uuid::randomHex(),
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            new Request(),
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayNotHasKey('swagShopId', $event->getParameters());
        static::assertArrayNotHasKey('appShopId', $event->getParameters());
    }

    public function testItDoesAddIconPackConfig(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme');

        $request = new Request();
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_THEME_NAME, 'SwagTheme');

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $event = new StorefrontRenderEvent(
            'testView',
            [],
            $request,
            $this->salesChannelContext
        );

        $eventDispatcher->dispatch($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
        static::assertEquals([
            'custom-icons' => [
                'path' => 'app/storefront/src/assets/icon-pack/custom-icons',
                'namespace' => 'SwagTheme',
            ],
        ], $event->getParameters()['themeIconConfig']);
    }

    public function testSubscribedEvents(): void
    {
        static::assertCount(2, (array) StorefrontSubscriber::getSubscribedEvents()[KernelEvents::EXCEPTION]);
    }
}
