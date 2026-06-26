<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeSchemaGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private ShopIdProvider&MockObject $shopIdProvider;

    private StatsService&Stub $statsService;

    private MigrationInfo&Stub $migrationInfo;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->statsService = static::createStub(StatsService::class);
        $this->migrationInfo = static::createStub(MigrationInfo::class);
        $this->eventDispatcher = new EventDispatcher();

        $shopId = ShopId::v2('shop-id');
        $this->shopIdProvider->expects($this->any())->method('getShopId')->willReturn($shopId);
    }

    public function testConfig(): void
    {
        $this->setEnvVars([
            'APP_URL' => 'https://app.url',
        ]);

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('version', $data);
        static::assertSame('6.6.9999999-dev', $data['version']);
        static::assertArrayHasKey('versionRevision', $data);
        static::assertSame('PHPUnit', $data['versionRevision']);
        static::assertArrayHasKey('adminWorker', $data);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('shop-id', $data['shopId']);
        static::assertArrayHasKey('appUrl', $data);
        static::assertSame('https://app.url', $data['appUrl']);

        $workerConfig = $data['adminWorker'];
        static::assertArrayHasKey('enableAdminWorker', $workerConfig);
        static::assertTrue($workerConfig['enableAdminWorker']);
        static::assertArrayHasKey('enableNotificationWorker', $workerConfig);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertArrayHasKey('transports', $workerConfig);
        static::assertIsArray($workerConfig['transports']);
        static::assertCount(1, $workerConfig['transports']);
        static::assertSame('slow', $workerConfig['transports'][0]);

        static::assertArrayHasKey('settings', $data);
        $settings = $data['settings'];
        static::assertIsArray($settings);
        static::assertArrayHasKey('enableUrlFeature', $settings);
        static::assertTrue($settings['enableUrlFeature']);
        static::assertArrayHasKey('appUrlReachable', $settings);
        static::assertFalse($settings['appUrlReachable']);
        static::assertArrayHasKey('appsRequireAppUrl', $settings);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertArrayHasKey('firstMigrationDate', $settings);
        static::assertTrue(
            $settings['firstMigrationDate'] === null
            || \is_string($settings['firstMigrationDate'])
        );
        static::assertArrayHasKey('private_allowed_extensions', $settings);
        static::assertFalse($settings['private_allowed_extensions']);
        static::assertArrayHasKey('enableHtmlSanitizer', $settings);
        static::assertTrue($settings['enableHtmlSanitizer']);
        static::assertArrayHasKey('minSearchTermLength', $settings);
        static::assertSame(2, $settings['minSearchTermLength']);

        static::assertArrayHasKey('inAppPurchases', $data);
        $inAppPurchases = $data['inAppPurchases'];
        static::assertIsArray($inAppPurchases);
        static::assertCount(1, $inAppPurchases);
        static::assertArrayHasKey('SwagApp', $inAppPurchases);
        static::assertSame(['SwagApp_premium'], $inAppPurchases['SwagApp']);
    }

    public function testReturnsCurrentShopIdIfShopIdFingerprintsHaveChanged(): void
    {
        $this->shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('current-shop-id'), new FingerprintComparisonResult([], [], 75)));

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('current-shop-id', $data['shopId']);
    }

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testConfigHidesWebhookTransportWhenWebhookReworkIsInactive(): void
    {
        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['async', 'low_priority'], $data['adminWorker']['transports']);
    }

    public function testConfigKeepsWebhookTransportWhenWebhookReworkIsActive(): void
    {
        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['webhook', 'async', 'low_priority'], $data['adminWorker']['transports']);
    }

    public function testConfigExtension(): void
    {
        $this->eventDispatcher->addListener(AdminInfoConfigEvent::class, static function (AdminInfoConfigEvent $event): void {
            $event->addConfig('foo', 'bar');
        });

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('foo', $data);
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

    #[TestDox('returns disabled message stats when stats service is not enabled')]
    public function testMessageStatsReturnsDisabledWhenNotEnabled(): void
    {
        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(enabled: false)
        );

        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertFalse($data['enabled']);
        static::assertNull($data['stats']);
    }

    #[TestDox('preserves floating-point precision in message stats response')]
    public function testMessageStatsPreservesFloatingPointPrecision(): void
    {
        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(
                true,
                new MessageStatsEntity(1, new \DateTime(), 1.00, new MessageTypeStatsCollection())
            )
        );
        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('stats', $data);
        static::assertArrayHasKey('averageTimeInQueue', $data['stats']);

        // Check that the floating point precision is preserved for zero-padded decimal values
        static::assertSame(1.00, $data['stats']['averageTimeInQueue']);
    }

    #[TestDox('returns empty types array when no element types are registered')]
    public function testContentSystemElementTypesReturnsEmptyWhenNoTypesRegistered(): void
    {
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);

        $controller = $this->createController(elementTypeRegistry: $registry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['types']);
    }

    #[TestDox('returns content system element types as JSON')]
    public function testContentSystemElementTypes(): void
    {
        $spec = new ContentSystemElementTypeSpecification(
            name: 'Sw:Alert',
            label: 'Alert',
            description: 'Alert component',
            icon: null,
            category: null,
            copilot: new CopilotSpecification('Alert summary', []),
            properties: [],
            slots: [],
            source: 'core',
        );

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn(['Sw:Alert' => $spec]);

        $controller = $this->createController(elementTypeRegistry: $registry);
        $response = $controller->getContentSystemElementTypes();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('types', $data);
        static::assertCount(1, $data['types']);
        static::assertSame('Sw:Alert', $data['types'][0]['name']);
        static::assertSame('core', $data['types'][0]['source']);
    }

    #[TestDox('folds the registered style options into the element types response')]
    public function testContentSystemElementTypesFoldsInStyleOptions(): void
    {
        $styleOptionRegistry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $styleOptionRegistry->method('allResolved')->willReturn(['col-span' => $this->styleOption()]);

        $controller = $this->createController(styleOptionRegistry: $styleOptionRegistry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('styleOptions', $data);
        // The folded section must carry the derived toSchema() shape, not raw option values
        static::assertSame('integer', $data['styleOptions']['col-span']['type']);
        static::assertSame(['min' => 1, 'max' => 12], $data['styleOptions']['col-span']['range']);
    }

    #[TestDox('encodes an empty style option set as a JSON object, not an array')]
    public function testContentSystemStyleOptionsEncodesEmptySetAsObject(): void
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn([]);

        $controller = $this->createController(styleOptionRegistry: $registry);
        $response = $controller->getContentSystemStyleOptions();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    #[TestDox('encodes the folded empty style option set as a JSON object on the element types response')]
    public function testContentSystemElementTypesEncodesEmptyStyleOptionsAsObject(): void
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn([]);

        $controller = $this->createController(styleOptionRegistry: $registry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    #[TestDox('returns the registered style options keyed by wire name with their derived schema')]
    public function testContentSystemStyleOptions(): void
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn(['col-span' => $this->styleOption()]);

        $controller = $this->createController(styleOptionRegistry: $registry);
        $response = $controller->getContentSystemStyleOptions();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([
            'col-span' => [
                'type' => 'integer',
                'enum' => null,
                'range' => ['min' => 1, 'max' => 12],
                'maxLength' => null,
                'default' => null,
                'adminUI' => null,
            ],
        ], $data['styleOptions']);
    }

    #[TestDox('returns empty data loader types when no loaders are registered')]
    public function testContentSystemDataLoaderTypesReturnsEmptyWhenNoLoaders(): void
    {
        $schemaGenerator = static::createStub(ContentSystemDataLoaderTypeSchemaGenerator::class);
        $schemaGenerator->method('getSchema')->willReturn(['sources' => []]);

        $controller = $this->createController(dataLoaderTypeSchemaGenerator: $schemaGenerator);
        $response = $controller->contentSystemDataLoaderTypes();

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(['sources' => []], json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
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

    #[TestDox('returns content system entity types as JSON')]
    public function testContentSystemEntityTypes(): void
    {
        $expected = ['entityTypes' => ['product', 'category', 'landing_page']];

        $rootSourceRegistry = static::createStub(RootSourceRegistry::class);
        $rootSourceRegistry->method('entityRootSources')->willReturn(['product', 'category', 'landing_page']);

        $controller = $this->createController(rootSourceRegistry: $rootSourceRegistry);
        $response = $controller->contentSystemEntityTypes();

        static::assertSame(200, $response->getStatusCode());
        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame($expected, json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
    }

    private function styleOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'col-span',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, ['min' => 1, 'max' => 12], null, null),
            null,
            'core',
        );
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

    /**
     * @param list<string> $adminWorkerTransports
     */
    private function createController(
        array $adminWorkerTransports = ['slow'],
        ?ContentSystemDataLoaderTypeSchemaGenerator $dataLoaderTypeSchemaGenerator = null,
        ?AbstractContentSystemElementTypeRegistry $elementTypeRegistry = null,
        ?AbstractContentSystemStyleOptionRegistry $styleOptionRegistry = null,
        ?RootSourceRegistry $rootSourceRegistry = null,
    ): InfoController {
        $parameterBag = new ParameterBag([
            'shopware.html_sanitizer.enabled' => true,
            'shopware.filesystem.private_allowed_extensions' => false,
            'shopware.admin_worker.transports' => $adminWorkerTransports,
            'shopware.admin_worker.enable_notification_worker' => true,
            'shopware.admin_worker.enable_queue_stats_worker' => true,
            'shopware.admin_worker.enable_admin_worker' => true,
            'kernel.shopware_version' => '6.6.9999999-dev',
            'kernel.shopware_version_revision' => 'PHPUnit',
            'shopware.media.enable_url_upload_feature' => true,
            'shopware.staging.administration.show_banner' => false,
            'shopware.deployment.runtime_extension_management' => true,
        ]);

        return new InfoController(
            static::createStub(DefinitionService::class),
            $parameterBag,
            static::createStub(BusinessEventCollector::class),
            static::createStub(IncrementGatewayRegistry::class),
            $this->migrationInfo,
            static::createStub(AppUrlVerifier::class),
            static::createStub(FlowActionCollector::class),
            new StaticSystemConfigService(),
            static::createStub(ApiRouteInfoResolver::class),
            StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]),
            $this->shopIdProvider,
            $this->statsService,
            $this->eventDispatcher,
            $dataLoaderTypeSchemaGenerator ?? static::createStub(ContentSystemDataLoaderTypeSchemaGenerator::class),
            $elementTypeRegistry ?? static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $styleOptionRegistry ?? static::createStub(AbstractContentSystemStyleOptionRegistry::class),
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
            null,
        );
    }
}
