<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeSchemaGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private ShopIdProvider&Stub $shopIdProvider;

    private StatsService&Stub $statsService;

    private MigrationInfo&Stub $migrationInfo;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shopIdProvider = static::createStub(ShopIdProvider::class);
        $this->statsService = static::createStub(StatsService::class);
        $this->migrationInfo = static::createStub(MigrationInfo::class);
        $this->eventDispatcher = new EventDispatcher();

        $this->shopIdProvider->method('getShopId')->willReturn(ShopId::v2('shop-id'));
    }

    #[TestDox('returns version and revision in config response')]
    public function testConfigReturnsVersionAndRevision(): void
    {
        $data = $this->getConfigData();

        static::assertSame('6.6.9999999-dev', $data['version']);
        static::assertSame('PHPUnit', $data['versionRevision']);
    }

    #[TestDox('returns shop ID in config response')]
    public function testConfigReturnsShopId(): void
    {
        $data = $this->getConfigData();

        static::assertSame('shop-id', $data['shopId']);
    }

    #[TestDox('returns app URL from environment in config response')]
    public function testConfigReturnsAppUrl(): void
    {
        $this->setEnvVars(['APP_URL' => 'https://app.url']);

        $data = $this->getConfigData();

        static::assertSame('https://app.url', $data['appUrl']);
    }

    #[TestDox('returns admin worker configuration in config response')]
    public function testConfigReturnsAdminWorkerConfig(): void
    {
        $data = $this->getConfigData();

        $workerConfig = $data['adminWorker'];
        static::assertTrue($workerConfig['enableAdminWorker']);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertSame(['slow'], $workerConfig['transports']);
    }

    #[TestDox('returns bundle entrypoints in config response')]
    public function testConfigReturnsBundlesWithEntrypoints(): void
    {
        $data = $this->getConfigData();

        $bundles = $data['bundles'];
        static::assertCount(1, $bundles);
        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $bundles);

        $bundle = $bundles['AdminExtensionApiPluginWithLocalEntryPoint'];
        static::assertSame([], $bundle['css']);
        static::assertSame([], $bundle['js']);
        static::assertSame('/admin/adminextensionapipluginwithlocalentrypoint/index.html', $bundle['baseUrl']);
        static::assertSame('plugin', $bundle['type']);
    }

    #[TestDox('returns settings flags in config response')]
    public function testConfigReturnsSettings(): void
    {
        $data = $this->getConfigData();

        $settings = $data['settings'];
        static::assertTrue($settings['enableUrlFeature']);
        static::assertFalse($settings['appUrlReachable']);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertFalse($settings['private_allowed_extensions']);
        static::assertTrue($settings['enableHtmlSanitizer']);
    }

    #[TestDox('returns in-app purchases in config response')]
    public function testConfigReturnsInAppPurchases(): void
    {
        $data = $this->getConfigData();

        $inAppPurchases = $data['inAppPurchases'];
        static::assertCount(1, $inAppPurchases);
        static::assertSame(['SwagApp_premium'], $inAppPurchases['SwagApp']);
    }

    #[TestDox('allows extending config via AdminInfoConfigEvent')]
    public function testConfigExtension(): void
    {
        $this->eventDispatcher->addListener(AdminInfoConfigEvent::class, static function (AdminInfoConfigEvent $event): void {
            $event->addConfig('foo', 'bar');
        });

        $data = $this->getConfigData();

        static::assertSame('bar', $data['foo']);
    }

    #[DataProvider('returnsFirstMigrationDateProvider')]
    #[TestDox('returns first migration date as $_dataName')]
    public function testConfigReturnsFirstMigrationDate(?string $migrationDate, mixed $expected): void
    {
        $this->migrationInfo->method('getFirstMigrationDate')->willReturn($migrationDate);

        $data = $this->getConfigData();

        static::assertSame($expected, $data['settings']['firstMigrationDate']);
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function returnsFirstMigrationDateProvider(): iterable
    {
        yield 'null when migration info returns null' => [null, null];
        yield 'date string from migration info' => ['2020-01-01T00:00:00.123+00:00', '2020-01-01T00:00:00.123+00:00'];
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('includes queue stats worker flag when legacy feature is inactive')]
    public function testConfigIncludesQueueStatsWorkerWhenLegacyFlagInactive(): void
    {
        $data = $this->getConfigData();

        static::assertTrue($data['adminWorker']['enableQueueStatsWorker']);
    }

    #[TestDox('returns current shop ID when fingerprint comparison suggests change')]
    public function testReturnsCurrentShopIdIfShopIdFingerprintsHaveChanged(): void
    {
        $this->shopIdProvider
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('current-shop-id'), new FingerprintComparisonResult([], [], 75)));

        $data = $this->getConfigData();

        static::assertSame('current-shop-id', $data['shopId']);
    }

    #[TestDox('preserves floating-point precision in message stats response')]
    public function testMessageStatsPreservesFloatingPointPrecision(): void
    {
        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(
                true,
                new MessageStatsEntity(1, new \DateTime('2024-01-01T00:00:00+00:00'), 1.00, new MessageTypeStatsCollection())
            )
        );

        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertSame(1.00, $data['stats']['averageTimeInQueue']);
    }

    #[TestDox('returns content system data loader type schema as JSON')]
    public function testContentSystemDataLoaderTypes(): void
    {
        $expected = [
            'sources' => [
                'navigation' => [
                    'types' => [['className' => 'Shopware\\Core\\Content\\Category\\Tree\\Tree']],
                ],
            ],
        ];

        $schemaGenerator = static::createStub(ContentSystemDataLoaderTypeSchemaGenerator::class);
        $schemaGenerator->method('getSchema')->willReturn($expected);

        $controller = $this->createController(dataLoaderTypeSchemaGenerator: $schemaGenerator);
        $response = $controller->contentSystemDataLoaderTypes();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame($expected, json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigData(): array
    {
        $content = $this->createController()
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();

        static::assertIsString($content);

        return json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function createController(?ContentSystemDataLoaderTypeSchemaGenerator $dataLoaderTypeSchemaGenerator = null): InfoController
    {
        $parameterBag = new ParameterBag([
            'shopware.html_sanitizer.enabled' => true,
            'shopware.filesystem.private_allowed_extensions' => false,
            'shopware.admin_worker.transports' => ['slow'],
            'shopware.admin_worker.enable_notification_worker' => true,
            'shopware.admin_worker.enable_queue_stats_worker' => true,
            'shopware.admin_worker.enable_admin_worker' => true,
            'kernel.shopware_version' => '6.6.9999999-dev',
            'kernel.shopware_version_revision' => 'PHPUnit',
            'shopware.media.enable_url_upload_feature' => true,
            'shopware.staging.administration.show_banner' => false,
            'shopware.deployment.runtime_extension_management' => true,
        ]);

        $kernel = new StubKernel([
            new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
        ]);

        $routerStub = static::createStub(RouterInterface::class);
        $routerStub->method('generate')->willReturn('/admin/adminextensionapipluginwithlocalentrypoint/index.html');

        $viteAccessor = new ViteFileAccessorDecorator(
            [],
            static::createStub(UrlPackage::class),
            $kernel,
            new Filesystem(),
        );

        return new InfoController(
            static::createStub(DefinitionService::class),
            $parameterBag,
            $kernel,
            static::createStub(BusinessEventCollector::class),
            static::createStub(IncrementGatewayRegistry::class),
            static::createStub(Connection::class),
            $this->migrationInfo,
            static::createStub(AppUrlVerifier::class),
            $routerStub,
            static::createStub(FlowActionCollector::class),
            new StaticSystemConfigService(),
            static::createStub(ApiRouteInfoResolver::class),
            StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]),
            $viteAccessor,
            new Filesystem(),
            $this->shopIdProvider,
            $this->statsService,
            $this->eventDispatcher,
            $dataLoaderTypeSchemaGenerator ?? static::createStub(ContentSystemDataLoaderTypeSchemaGenerator::class),
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
        );
    }
}

/**
 * @internal
 */
class AdminExtensionApiPluginWithLocalEntryPoint extends Plugin
{
    public function getPath(): string
    {
        return __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint';
    }
}
