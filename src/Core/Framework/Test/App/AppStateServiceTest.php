<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\ThemeService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use function Flag\skipTestNext10286;

/**
 * @group ThemeCompile
 */
class AppStateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;
    use StorefrontAppRegistryTestBehaviour;

    /**
     * @var ThemeService
     */
    private $themeService;

    /**
     * @var EntityRepository
     */
    private $appRepo;

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

    public function setUp(): void
    {
        skipTestNext10286($this);
        $this->themeService = $this->getContainer()->get(ThemeService::class);
        $this->appRepo = $this->getContainer()->get('app.repository');
        $this->themeRepo = $this->getContainer()->get('theme.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
    }

    public function testNotFoundAppThrowsOnActivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->activateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testNotFoundAppThrowsOnDectivate(): void
    {
        static::expectException(AppNotFoundException::class);
        $this->appStateService->deactivateApp(Uuid::randomHex(), Context::createDefaultContext());
    }

    // ToDo: reactivate once ThemeHandling is migrated
//    public function testAppWithAThemeInUseCannotBeDeactivated(): void
//    {
//        $context = Context::createDefaultContext();
//        $this->loadAppsFromDir(__DIR__ . '/../../../Storefront/_fixtures/theme');
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
//        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();
//        $salesChannelId = $this->createSalesChannel();
//
//        $this->themeService->assignTheme($themeId, $salesChannelId, $context);
//
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
//        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
//
//        static::expectException(ThemeAssignmentException::class);
//
//        $this->appStateService->deactivateApp($appId, $context);
//    }
//
//    public function testAppWithAChildThemeInUseCannotBeDeactivated(): void
//    {
//        $context = Context::createDefaultContext();
//        $this->loadAppsFromDir(__DIR__ . '/../../../Storefront/_fixtures/theme');
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
//        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();
//
//        $childId = Uuid::randomHex();
//        $childTheme = [
//            'id' => $childId,
//            'name' => 'child',
//            'author' => 'author',
//            'active' => true,
//        ];
//
//        $this->themeRepo->upsert([[
//            'id' => $themeId,
//            'childThemes' => [$childTheme],
//        ]], $context);
//
//        $salesChannelId = $this->createSalesChannel();
//
//        $this->themeService->assignTheme($childId, $salesChannelId, $context);
//
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
//        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
//
//        static::expectException(ThemeAssignmentException::class);
//
//        $this->appStateService->deactivateApp($appId, $context);
//    }
//
//    public function testAppWithAThemeCanBeDeactivated(): void
//    {
//        $this->loadAppsFromDir(__DIR__ . '/../../../Storefront/_fixtures/theme');
//        $context = Context::createDefaultContext();
//
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
//        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
//
//        $eventWasReceived = false;
//        $onAppDeactivation = function (AppDeactivatedEvent $event) use (&$eventWasReceived, $appId, $context): void {
//            $eventWasReceived = true;
//            static::assertEquals($appId, $event->getAppId());
//            static::assertEquals($context, $event->getContext());
//        };
//        $this->eventDispatcher->addListener(AppDeactivatedEvent::class, $onAppDeactivation);
//
//        $this->appStateService->deactivateApp($appId, $context);
//
//        static::assertTrue($eventWasReceived);
//        $this->eventDispatcher->removeListener(AppDeactivatedEvent::class, $onAppDeactivation);
//    }
//
//    public function testAppWithAThemeCanBeActivated(): void
//    {
//        $this->loadAppsFromDir(__DIR__ . '/../../../Storefront/_fixtures/theme', false);
//        $context = Context::createDefaultContext();
//
//        $criteria = new Criteria();
//        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
//        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();
//
//        $eventWasReceived = false;
//        $onAppActivation = function (AppActivatedEvent $event) use (&$eventWasReceived, $appId, $context): void {
//            $eventWasReceived = true;
//            static::assertEquals($appId, $event->getAppId());
//            static::assertEquals($context, $event->getContext());
//        };
//        $this->eventDispatcher->addListener(AppActivatedEvent::class, $onAppActivation);
//
//        $this->appStateService->activateApp($appId, $context);
//
//        static::assertTrue($eventWasReceived);
//        $this->eventDispatcher->removeListener(AppActivatedEvent::class, $onAppActivation);
//    }
//
//    private function createSalesChannel(): string
//    {
//        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
//
//        $id = Uuid::randomHex();
//        $payload = [[
//            'id' => $id,
//            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
//            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
//            'languageId' => Defaults::LANGUAGE_SYSTEM,
//            'currencyId' => Defaults::CURRENCY,
//            'active' => true,
//            'currencyVersionId' => Defaults::LIVE_VERSION,
//            'paymentMethodId' => $this->getValidPaymentMethodId(),
//            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
//            'shippingMethodId' => $this->getValidShippingMethodId(),
//            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
//            'navigationCategoryId' => $this->getValidCategoryId(),
//            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
//            'countryId' => $this->getValidCountryId(),
//            'countryVersionId' => Defaults::LIVE_VERSION,
//            'currencies' => [['id' => Defaults::CURRENCY]],
//            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
//            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
//            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
//            'countries' => [['id' => $this->getValidCountryId()]],
//            'name' => 'first sales-channel',
//            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
//        ]];
//
//        $salesChannelRepository->create($payload, Context::createDefaultContext());
//
//        return $id;
//    }
}
