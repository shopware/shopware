<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Theme\Exception\ThemeAssignmentException;
use Shopware\Storefront\Theme\ThemeService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcher;

/**
 * @internal
 */
class AppStateServiceThemeTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    private ?ThemeService $themeService;

    private EntityRepository $appRepo;

    private ?EntityRepository $themeRepo;

    private AppStateService $appStateService;

    private TraceableEventDispatcher $eventDispatcher;

    private EntityRepository $templateRepo;

    protected function setUp(): void
    {
        $this->themeService = $this->getContainer()->get(ThemeService::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->appRepo = $this->getContainer()->get('app.repository');
        $this->themeRepo = $this->getContainer()->get('theme.repository', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->templateRepo = $this->getContainer()->get('app_template.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
    }

    public function testAppWithAThemeInUseCannotBeDeactivated(): void
    {
        if (!$this->themeService) {
            static::markTestSkipped('AppThemeServiceTest needs storefront to be installed.');
        }
        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        /* @phpstan-ignore-next-line */
        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();
        $salesChannelId = $this->createSalesChannel();

        /* @phpstan-ignore-next-line */
        $this->themeService->assignTheme($themeId, $salesChannelId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();

        static::expectException(ThemeAssignmentException::class);
        /* @phpstan-ignore-next-line */
        $this->appStateService->deactivateApp($appId, $context);
    }

    public function testAppWithAChildThemeInUseCannotBeDeactivated(): void
    {
        if (!$this->themeService) {
            static::markTestSkipped('AppThemeServiceTest needs storefront to be installed.');
        }
        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        /* @phpstan-ignore-next-line */
        $themeId = $this->themeRepo->searchIds($criteria, $context)->firstId();

        $childId = Uuid::randomHex();
        $childTheme = [
            'id' => $childId,
            'name' => 'child',
            'parentThemeId' => $themeId,
            'author' => 'author',
            'active' => true,
        ];

        /* @phpstan-ignore-next-line */
        $this->themeRepo->upsert([[
            'id' => $themeId,
            'dependentThemes' => [$childTheme],
        ]], $context);

        $salesChannelId = $this->createSalesChannel();

        /* @phpstan-ignore-next-line */
        $this->themeService->assignTheme($childId, $salesChannelId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        /** @var string $appId */
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();

        static::expectException(ThemeAssignmentException::class);
        $this->appStateService->deactivateApp($appId, $context);
    }

    public function testAppWithAThemeCanBeDeactivated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        /** @var string $appId */
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();

        $eventWasReceived = false;
        $onAppDeactivation = function (AppDeactivatedEvent $event) use (&$eventWasReceived, $appId, $context): void {
            $eventWasReceived = true;
            static::assertEquals($appId, $event->getApp()->getId());
            static::assertEquals($context, $event->getContext());
        };
        $this->eventDispatcher->addListener(AppDeactivatedEvent::class, $onAppDeactivation);

        $this->appStateService->deactivateApp($appId, $context);

        static::assertTrue($eventWasReceived);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        static::assertEquals(0, $this->templateRepo->search($criteria, $context)->getTotal());

        $this->eventDispatcher->removeListener(AppDeactivatedEvent::class, $onAppDeactivation);
    }

    public function testAppWithAThemeCanBeActivated(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../../Theme/fixtures/Apps/theme', false);
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'SwagTheme'));
        /** @var string $appId */
        $appId = $this->appRepo->searchIds($criteria, $context)->firstId();

        $eventWasReceived = false;
        $onAppActivation = function (AppActivatedEvent $event) use (&$eventWasReceived, $appId, $context): void {
            $eventWasReceived = true;
            static::assertEquals($appId, $event->getApp()->getId());
            static::assertEquals($context, $event->getContext());
        };
        $this->eventDispatcher->addListener(AppActivatedEvent::class, $onAppActivation);

        $this->appStateService->activateApp($appId, $context);

        static::assertTrue($eventWasReceived);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        // We expect 1 storefront twig template and svg image to be stored in the DB
        $expectedTemplates = 2;
        if (!$this->themeService) {
            // if the storefront is not installed we only expect the storefront twig template
            // as the assets can not be imported without parsing the theme.json file
            $expectedTemplates = 1;
        }
        static::assertEquals($expectedTemplates, $this->templateRepo->search($criteria, $context)->getTotal());

        $this->eventDispatcher->removeListener(AppActivatedEvent::class, $onAppActivation);
    }

    private function createSalesChannel(): string
    {
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $id = Uuid::randomHex();
        $payload = [[
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'active' => true,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]];

        $salesChannelRepository->create($payload, Context::createDefaultContext());

        return $id;
    }
}
