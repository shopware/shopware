<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AppStateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontPluginRegistryTestBehaviour;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepository
     */
    private $appRepository;

    /**
     * @var EntityRepository
     */
    private $themeRepo;

    /**
     * @var AppStateService
     */
    private $appStateService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AppLifecycle
     */
    private $appLifecycle;

    /**
     * @var Context
     */
    private $context;

    public function setUp(): void
    {
        $this->themeService = $this->getContainer()->get(ThemeService::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->themeRepo = $this->getContainer()->get('theme.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->context = Context::createDefaultContext();
    }

    public function testNotFoundAppThrowsOnActivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->activateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testNotFoundAppThrowsOnDeactivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->deactivateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testActivate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, false, $this->context);
        $appId = $this->appRepository->searchIds(new Criteria(), $this->context)->firstId();
        $this->assertAppState($appId, false);

        $eventWasReceived = false;
        $onAppInstalled = function (AppActivatedEvent $event) use ($appId, &$eventWasReceived): void {
            $eventWasReceived = true;
            static::assertSame($appId, $event->getApp()->getId());
        };
        $this->eventDispatcher->addListener(AppActivatedEvent::class, $onAppInstalled);
        $this->appStateService->activateApp($appId, $this->context);
        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppActivatedEvent::class, $onAppInstalled);

        $this->assertAppState($appId, true);
    }

    public function testDeactivate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/Manifest/_fixtures/test/manifest.xml');
        $this->appLifecycle->install($manifest, true, $this->context);
        $appId = $this->appRepository->searchIds(new Criteria(), $this->context)->firstId();
        $this->assertAppState($appId, true);

        $eventWasReceived = false;
        $onAppInstalled = function (AppDeactivatedEvent $event) use ($appId, &$eventWasReceived): void {
            $eventWasReceived = true;
            static::assertSame($appId, $event->getApp()->getId());
        };
        $this->eventDispatcher->addListener(AppDeactivatedEvent::class, $onAppInstalled);
        $this->appStateService->deactivateApp($appId, $this->context);
        static::assertTrue($eventWasReceived);
        $this->eventDispatcher->removeListener(AppDeactivatedEvent::class, $onAppInstalled);

        $this->assertAppState($appId, false);
    }

    private function assertAppState(string $appId, bool $active): void
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('templates');
        $criteria->addAssociation('paymentMethods.paymentMethod');

        /** @var AppEntity|null $app */
        $app = $this->appRepository->search($criteria, $this->context)->first();
        static::assertNotNull($app);
        static::assertSame($active, $app->isActive());
        $this->assertDefaultTemplate($app);
        $this->assertDefaultPaymentMethods($app);
    }

    private function assertDefaultTemplate(AppEntity $app): void
    {
        $template = $app->getTemplates()->first();
        static::assertNotNull($template);
        static::assertSame($app->isActive(), $template->isActive());
    }

    private function assertDefaultPaymentMethods(AppEntity $app): void
    {
        static::assertCount(2, $app->getPaymentMethods());
        foreach ($app->getPaymentMethods() as $appPaymentMethod) {
            $paymentMethod = $appPaymentMethod->getPaymentMethod();
            static::assertNotNull($paymentMethod);
            static::assertSame($app->isActive(), $paymentMethod->getActive());
        }
    }
}
