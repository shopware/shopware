<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\HttpFoundation\Request;

class StorefrontSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
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

        static::assertArrayHasKey('swagShopId', $event->getParameters());
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

        /** @var SystemConfigService $systemConfigService */
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
}
