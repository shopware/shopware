<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\StoredSchemaResolver;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageStatsResponseEntity;
use Shopware\Core\Framework\MessageQueue\Stats\Entity\MessageTypeStatsCollection;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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
        $this->shopIdProvider->method('getShopId')->willReturn($shopId);
    }

    #[TestDox('returns content system element types as JSON')]
    public function testContentSystemElementTypes(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $spec = $this->alertTypeSpecification();

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

    #[TestDox('returns content system entity types as JSON')]
    public function testContentSystemEntityTypes(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

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

    #[TestDox('returns the registered style options keyed by wire name with their derived schema')]
    public function testContentSystemStyleOptionsReturnsRegisteredOptionsKeyedByWireName(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

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
                'breakpointAware' => true,
                'adminUI' => null,
            ],
        ], $data['styleOptions']);
    }

    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresMessageQueueStatsReadPrivilege(string $routeName): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $route = (new AttributeRouteControllerLoader())->load(InfoController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, InfoController::class));
        static::assertSame(['message_queue_stats:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    #[TestDox('returns current shop id when shop id fingerprints have changed')]
    public function testReturnsCurrentShopIdIfShopIdFingerprintsHaveChanged(): void
    {
        $this->shopIdProvider
            ->expects($this->atLeastOnce())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('current-shop-id'), new FingerprintComparisonResult([], [], 75)));

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('current-shop-id', $data['shopId']);
    }

    #[TestDox('folds the registered binding specifications into the matching element type entry, keyed by source-qualified id')]
    public function testContentSystemElementTypesFoldsInBindingSpecifications(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $imageSpec = new ContentSystemElementTypeSpecification(
            name: 'Sw:Media:Image',
            label: 'Image',
            description: 'Image component',
            icon: null,
            category: null,
            copilot: new CopilotSpecification('Image summary', []),
            properties: [],
            slots: [],
            source: 'core',
        );
        $alertSpec = $this->alertTypeSpecification();

        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn(['Sw:Media:Image' => $imageSpec, 'Sw:Alert' => $alertSpec]);

        $bindingSpecificationRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingSpecificationRegistry->method('all')->willReturn(['core:media-picker' => $this->bindingSpecification('Sw:Media:Image')]);

        $controller = $this->createController(elementTypeRegistry: $elementTypeRegistry, bindingSpecificationRegistry: $bindingSpecificationRegistry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $typesByName = [];
        foreach ($data['types'] as $type) {
            $typesByName[$type['name']] = $type;
        }

        static::assertSame([
            'core:media-picker' => [
                'id' => 'media-picker',
                'type' => 'Sw:Media:Image',
                'label' => 'Media Picker',
                'default' => false,
                'resolves' => [],
                'inputs' => [],
            ],
        ], $typesByName['Sw:Media:Image']['bindingSpecifications']);
        // A type with no applicable specification carries an empty map, not the Image entry's specifications.
        static::assertSame([], $typesByName['Sw:Alert']['bindingSpecifications']);
    }

    #[TestDox('folds the resolved storage schema into each element type entry, keyed by stored key')]
    public function testContentSystemElementTypesFoldsInStorageSchema(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $textSpec = ContentSystemElementTypeSpecificationBuilder::create('Sw:Content:Text')
            ->primitive('text', 'string', default: '<p>Lorem ipsum</p>')
            ->build();
        $alertSpec = $this->alertTypeSpecification();

        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn(['Sw:Content:Text' => $textSpec, 'Sw:Alert' => $alertSpec]);

        $controller = $this->createController(elementTypeRegistry: $elementTypeRegistry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $typesByName = [];
        foreach ($data['types'] as $type) {
            $typesByName[$type['name']] = $type;
        }

        static::assertSame([
            'text' => ['kind' => 'property', 'type' => 'string', 'required' => false, 'default' => '<p>Lorem ipsum</p>'],
        ], $typesByName['Sw:Content:Text']['storageSchema']);
        // A type that stores nothing carries an empty map, not the Text entry's storage schema.
        static::assertSame([], $typesByName['Sw:Alert']['storageSchema']);
    }

    #[TestDox('folds the registered style options into the element types response')]
    public function testContentSystemElementTypesFoldsInStyleOptions(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

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
        static::assertTrue($data['styleOptions']['col-span']['breakpointAware']);
    }

    #[TestDox('preserves floating-point precision in message stats response')]
    public function testMessageStatsPreservesFloatingPointPrecision(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(
                true,
                new MessageStatsEntity(1, new \DateTime('2024-01-15 10:00:00'), 1.00, new MessageTypeStatsCollection())
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

    #[TestDox('returns the complete admin config payload with all expected keys and values')]
    public function testConfig(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $this->setEnvVars([
            'APP_URL' => 'https://app.url',
        ]);

        $appUrlVerifier = static::createStub(AppUrlVerifier::class);
        $appUrlVerifier->method('isAppUrlReachable')->willReturn(true);
        $appUrlVerifier->method('hasAppsThatNeedAppUrl')->willReturn(false);

        $content = $this->createController(appUrlVerifier: $appUrlVerifier)->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
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
        static::assertTrue($settings['appUrlReachable']);
        static::assertArrayHasKey('appsRequireAppUrl', $settings);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertArrayHasKey('firstMigrationDate', $settings);
        static::assertNull($settings['firstMigrationDate']);
        static::assertArrayHasKey('private_allowed_extensions', $settings);
        static::assertSame(['pdf', 'epub'], $settings['private_allowed_extensions']);
        static::assertArrayHasKey('private_allowed_mime_types_by_extension', $settings);
        static::assertIsArray($settings['private_allowed_mime_types_by_extension']);
        static::assertContains('application/pdf', $settings['private_allowed_mime_types_by_extension']['pdf']);
        static::assertSame(['application/epub+zip'], $settings['private_allowed_mime_types_by_extension']['epub']);
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

    public function testConfigExtension(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

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

    #[DisabledFeatures(['WEBHOOKS_REWORK'])]
    public function testConfigHidesWebhookTransportWhenWebhookReworkIsInactive(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['async', 'low_priority'], $data['adminWorker']['transports']);
    }

    public function testConfigKeepsWebhookTransportWhenWebhookReworkIsActive(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $content = $this->createController(['webhook', 'async', 'low_priority'])
            ->config(Context::createDefaultContext(), Request::create('http://localhost'))
            ->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['webhook', 'async', 'low_priority'], $data['adminWorker']['transports']);
    }

    #[DataProvider('returnsFirstMigrationDateProvider')]
    #[TestDox('returns first migration date as $_dataName')]
    public function testConfigReturnsFirstMigrationDate(?string $migrationDate, mixed $expected): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');
        $this->migrationInfo->method('getFirstMigrationDate')->willReturn($migrationDate);

        $data = $this->getConfigData();

        static::assertSame($expected, $data['settings']['firstMigrationDate']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('includes queue stats worker flag when legacy feature is inactive')]
    public function testConfigIncludesQueueStatsWorkerWhenLegacyFlagInactive(): void
    {
        $this->shopIdProvider->expects($this->atLeastOnce())->method('getShopId');

        $data = $this->getConfigData();

        static::assertTrue($data['adminWorker']['enableQueueStatsWorker']);
    }

    #[TestDox('returns disabled message stats when stats service is not enabled')]
    public function testMessageStatsReturnsDisabledWhenNotEnabled(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $this->statsService->method('getStats')->willReturn(
            new MessageStatsResponseEntity(enabled: false)
        );

        $content = $this->createController()->messageStats()->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertFalse($data['enabled']);
        static::assertNull($data['stats']);
    }

    #[TestDox('returns empty types array when no element types are registered')]
    public function testContentSystemElementTypesReturnsEmptyWhenNoTypesRegistered(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('all')->willReturn([]);

        $controller = $this->createController(elementTypeRegistry: $registry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['types']);
    }

    #[TestDox('encodes the folded per-type binding specification set as a JSON object when the type has none')]
    public function testContentSystemElementTypesEncodesEmptyBindingSpecificationsAsObject(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $spec = $this->alertTypeSpecification();

        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn(['Sw:Alert' => $spec]);

        $controller = $this->createController(elementTypeRegistry: $elementTypeRegistry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"bindingSpecifications":{}', $content);
    }

    #[TestDox('encodes the folded per-type storage schema as a JSON object when the type stores nothing')]
    public function testContentSystemElementTypesEncodesEmptyStorageSchemaAsObject(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $spec = $this->alertTypeSpecification();

        $elementTypeRegistry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $elementTypeRegistry->method('all')->willReturn(['Sw:Alert' => $spec]);

        $controller = $this->createController(elementTypeRegistry: $elementTypeRegistry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"storageSchema":{}', $content);
    }

    #[TestDox('encodes the folded empty style option set as a JSON object on the element types response')]
    public function testContentSystemElementTypesEncodesEmptyStyleOptionsAsObject(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn([]);

        $controller = $this->createController(styleOptionRegistry: $registry);
        $response = $controller->getContentSystemElementTypes();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    #[TestDox('encodes an empty style option set as a JSON object, not an array')]
    public function testContentSystemStyleOptionsEncodesEmptySetAsObject(): void
    {
        $this->shopIdProvider->expects($this->never())->method('getShopId');

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('allResolved')->willReturn([]);

        $controller = $this->createController(styleOptionRegistry: $registry);
        $response = $controller->getContentSystemStyleOptions();

        $content = $response->getContent();
        static::assertIsString($content);
        // Assert the raw encoding: json_decode would erase the {} vs [] distinction
        static::assertStringContainsString('"styleOptions":{}', $content);
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function returnsFirstMigrationDateProvider(): iterable
    {
        yield 'null when migration info returns null' => [null, null];
        yield 'date string from migration info' => ['2020-01-01T00:00:00.123+00:00', '2020-01-01T00:00:00.123+00:00'];
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'queue stats' => ['api.info.queue'];
        yield 'message stats' => ['api.info.message-stats'];
    }

    private function bindingSpecification(string $type = 'media-gallery'): BindingSpecification
    {
        return new BindingSpecification('media-picker', $type, 'Media Picker', [], [], 'core');
    }

    private function alertTypeSpecification(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
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
    }

    private function styleOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'col-span',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, ['min' => 1, 'max' => 12], null, null),
            true,
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
        ?ContentSystemDataLoaderSchemaGenerator $dataLoaderSchemaGenerator = null,
        ?AbstractContentSystemElementTypeRegistry $elementTypeRegistry = null,
        ?AbstractContentSystemStyleOptionRegistry $styleOptionRegistry = null,
        ?RootSourceRegistry $rootSourceRegistry = null,
        ?AbstractContentSystemBindingSpecificationRegistry $bindingSpecificationRegistry = null,
        ?StoredSchemaResolver $storedSchemaResolver = null,
        ?AppUrlVerifier $appUrlVerifier = null,
    ): InfoController {
        $parameterBag = new ParameterBag([
            'shopware.html_sanitizer.enabled' => true,
            'shopware.filesystem.allowed_extensions' => [],
            'shopware.filesystem.private_allowed_extensions' => ['pdf', 'epub'],
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
            $appUrlVerifier ?? static::createStub(AppUrlVerifier::class),
            static::createStub(FlowActionCollector::class),
            new StaticSystemConfigService(),
            static::createStub(ApiRouteInfoResolver::class),
            StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]),
            $this->shopIdProvider,
            $this->statsService,
            $this->eventDispatcher,
            $dataLoaderSchemaGenerator ?? static::createStub(ContentSystemDataLoaderSchemaGenerator::class),
            $elementTypeRegistry ?? static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $styleOptionRegistry ?? static::createStub(AbstractContentSystemStyleOptionRegistry::class),
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
            $bindingSpecificationRegistry ?? static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            $storedSchemaResolver ?? new StoredSchemaResolver(
                static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
                static::createStub(DataLoaderProvider::class),
            ),
            null,
            new MediaFileExtensionListProvider($this->eventDispatcher, [], ['pdf', 'epub']),
        );
    }
}
