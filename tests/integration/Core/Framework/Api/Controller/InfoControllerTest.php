<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Messenger\Stamp\SentAtStamp;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Event\CustomAppEvent;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\A11yRenderedDocumentAware;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\MessageQueue\Stats\StatsService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\Framework\BundleFixture;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    use AppSystemTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetConfig(): void
    {
        $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();

        $expected = [
            'version' => '6.7.9999999.9999999-dev',
            'shopId' => $shopId,
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => true,
                'enableQueueStatsWorker' => true,
                'enableNotificationWorker' => true,
                'transports' => ['async', 'low_priority'],
            ],
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => true,
                'appUrlReachable' => true,
                'appsRequireAppUrl' => false,
                'private_allowed_extensions' => [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'avif',
                    'gif',
                    'svg',
                    'bmp',
                    'tiff',
                    'tif',
                    'eps',
                    'webm',
                    'mkv',
                    'flv',
                    'ogv',
                    'ogg',
                    'mov',
                    'mp4',
                    'avi',
                    'wmv',
                    'pdf',
                    'aac',
                    'mp3',
                    'wav',
                    'flac',
                    'oga',
                    'wma',
                    'txt',
                    'doc',
                    'docx',
                    'ico',
                    'glb',
                    'zip',
                    'rar',
                    'csv',
                    'xls',
                    'xlsx',
                    'html',
                    'xml',
                    'vtt',
                    'srt',
                    'sub',
                    'ass',
                    'ssa',
                ],
                'enableHtmlSanitizer' => true,
                'enableStagingMode' => false,
                'disableExtensionManagement' => false,
            ],
            'inAppPurchases' => [],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // reset environment-based mismatch
        $decodedResponse['bundles'] = [];
        $decodedResponse['versionRevision'] = $expected['versionRevision'];

        static::assertSame($expected, $decodedResponse);
    }

    public function testGetConfigWithPermissions(): void
    {
        $ids = new IdsCollection();
        $appRepository = static::getContainer()->get('app.repository');
        $appRepository->create([
            [
                'name' => 'PHPUnit',
                'path' => '/foo/bar',
                'active' => true,
                'configurable' => false,
                'version' => '1.0.0',
                'label' => 'PHPUnit',
                'integration' => [
                    'id' => $ids->create('integration'),
                    'label' => 'foo',
                    'accessKey' => '123',
                    'secretAccessKey' => '456',
                ],
                'aclRole' => [
                    'name' => 'PHPUnitRole',
                    'privileges' => [
                        'user:create',
                        'user:read',
                        'user:update',
                        'user:delete',
                        'user_change_me',
                    ],
                ],
                'baseAppUrl' => 'https://example.com',
            ],
        ], Context::createDefaultContext());

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $bundle = [
            'active' => true,
            'integrationId' => $ids->get('integration'),
            'type' => 'app',
            'baseUrl' => 'https://example.com',
            'permissions' => [
                'create' => ['user'],
                'read' => ['user'],
                'update' => ['user'],
                'delete' => ['user'],
                'additional' => ['user_change_me'],
            ],
            'version' => '1.0.0',
            'name' => 'PHPUnit',
        ];

        $expected = [
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => true,
                'transports' => [],
            ],
            'bundles' => $bundle,
            'settings' => [
                'enableUrlFeature' => true,
                'enableHtmlSanitizer' => true,
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        foreach (array_keys($expected) as $key) {
            static::assertArrayHasKey($key, $decodedResponse);
        }

        $bundles = $decodedResponse['bundles'];
        static::assertIsArray($bundles);
        static::assertArrayHasKey('PHPUnit', $bundles);
        static::assertIsArray($bundles['PHPUnit']);
        static::assertSame($bundle, $bundles['PHPUnit']);
    }

    public function testGetShopwareVersion(): void
    {
        $expected = [
            'version' => '6.7.9999999.9999999-dev',
        ];

        $url = '/api/_info/version';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotEmpty($version);
        static::assertStringStartsWith($version, $content);
    }

    public function testGetShopwareVersionOldVersion(): void
    {
        $expected = [
            'version' => '6.7.9999999.9999999-dev',
        ];

        $url = '/api/v1/_info/version';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotEmpty($version);
        static::assertStringStartsWith($version, $content);
    }

    public function testBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'extensions' => [],
                'name' => 'checkout.customer.login',
                'class' => CustomerLoginEvent::class,
                'data' => [
                    'customer' => [
                        'type' => 'entity',
                        'entityClass' => CustomerDefinition::class,
                        'entityName' => 'customer',
                    ],
                    'contextToken' => [
                        'type' => 'string',
                    ],
                ],
                'aware' => [
                    ScalarValuesAware::class,
                    lcfirst((new \ReflectionClass(ScalarValuesAware::class))->getShortName()),
                    SalesChannelAware::class,
                    lcfirst((new \ReflectionClass(SalesChannelAware::class))->getShortName()),
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    CustomerAware::class,
                    lcfirst((new \ReflectionClass(CustomerAware::class))->getShortName()),
                ],
            ],
            [
                'extensions' => [],
                'name' => 'checkout.order.placed',
                'class' => CheckoutOrderPlacedEvent::class,
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
                        'entityName' => 'order',
                    ],
                ],
                'aware' => [
                    CustomerAware::class,
                    lcfirst((new \ReflectionClass(CustomerAware::class))->getShortName()),
                    CustomerGroupAware::class,
                    lcfirst((new \ReflectionClass(CustomerGroupAware::class))->getShortName()),
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    SalesChannelAware::class,
                    lcfirst((new \ReflectionClass(SalesChannelAware::class))->getShortName()),
                    OrderAware::class,
                    lcfirst((new \ReflectionClass(OrderAware::class))->getShortName()),
                ],
            ],
            [
                'extensions' => [],
                'name' => 'state_enter.order_delivery.state.shipped_partially',
                'class' => OrderStateMachineStateChangeEvent::class,
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
                        'entityName' => 'order',
                    ],
                ],
                'aware' => [
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    SalesChannelAware::class,
                    lcfirst((new \ReflectionClass(SalesChannelAware::class))->getShortName()),
                    OrderAware::class,
                    lcfirst((new \ReflectionClass(OrderAware::class))->getShortName()),
                    CustomerAware::class,
                    lcfirst((new \ReflectionClass(CustomerAware::class))->getShortName()),
                    A11yRenderedDocumentAware::class,
                    lcfirst((new \ReflectionClass(A11yRenderedDocumentAware::class))->getShortName()),
                ],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvents = array_values(array_filter($response, static fn ($x) => $x['name'] === $event['name']));
            sort($event['aware']);
            sort($actualEvents[0]['aware']);
            static::assertNotEmpty($actualEvents, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvents);
            static::assertSame($event, $actualEvents[0], $event['name']);
        }
    }

    public function testBundlePaths(): void
    {
        $kernel = new StubKernel([
            new BundleFixture('SomeFunctionalityBundle', __DIR__ . '/Fixtures/InfoController'),
        ]);

        $eventCollector = $this->createMock(FlowActionCollector::class);
        $infoController = new InfoController(
            $this->createMock(DefinitionService::class),
            new ParameterBag([
                'kernel.shopware_version' => 'shopware-version',
                'kernel.shopware_version_revision' => 'shopware-version-revision',
                'shopware.admin_worker.enable_admin_worker' => 'enable-admin-worker',
                'shopware.admin_worker.enable_queue_stats_worker' => 'enable-queue-stats-worker',
                'shopware.admin_worker.enable_notification_worker' => 'enable-notification-worker',
                'shopware.admin_worker.transports' => 'transports',
                'shopware.filesystem.private_allowed_extensions' => ['png'],
                'shopware.html_sanitizer.enabled' => true,
                'shopware.media.enable_url_upload_feature' => true,
                'shopware.staging.administration.show_banner' => true,
                'shopware.deployment.runtime_extension_management' => true,
            ]),
            $kernel,
            $this->createMock(BusinessEventCollector::class),
            static::getContainer()->get('shopware.increment.gateway.registry'),
            $this->connection,
            static::getContainer()->get(AppUrlVerifier::class),
            static::getContainer()->get('router'),
            $eventCollector,
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(ApiRouteInfoResolver::class),
            static::getContainer()->get(InAppPurchase::class),
            new ViteFileAccessorDecorator(
                [],
                static::getContainer()->get('shopware.asset.asset'),
                $kernel,
                new Filesystem(),
            ),
            new Filesystem(),
            static::getContainer()->get(ShopIdProvider::class),
            $this->createMock(StatsService::class),
        );

        $infoController->setContainer($this->createMock(Container::class));

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $content = $infoController->config(Context::createDefaultContext(), Request::create($appUrl))->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('SomeFunctionalityBundle', $config['bundles']);

        static::assertStringEndsWith(
            '/bundles/somefunctionality/administration/js/some-functionality-bundle.js',
            (string) $config['bundles']['SomeFunctionalityBundle']['js'][0]
        );
    }

    public function testBaseAdminPaths(): void
    {
        if (!class_exists(AdministrationController::class)) {
            static::markTestSkipped('Cannot test without Administration as results will differ');
        }

        $this->clearRequestStack();

        $this->loadAppsFromDir(__DIR__ . '/Fixtures/AdminExtensionApiApp');

        $kernel = new StubKernel([
            new AdminExtensionApiBundle(),
            new AdminExtensionApiWithoutSelfKnownBaseUrlBundle(),
            new AdminExtensionApiPlugin(true, __DIR__ . '/Fixtures/InfoController'),
            new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
        ]);

        $eventCollector = $this->createMock(FlowActionCollector::class);

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $infoController = new InfoController(
            $this->createMock(DefinitionService::class),
            new ParameterBag([
                'kernel.shopware_version' => 'shopware-version',
                'kernel.shopware_version_revision' => 'shopware-version-revision',
                'shopware.admin_worker.enable_admin_worker' => 'enable-admin-worker',
                'shopware.admin_worker.enable_queue_stats_worker' => 'enable-queue-stats-worker',
                'shopware.admin_worker.enable_notification_worker' => 'enable-notification-worker',
                'shopware.admin_worker.transports' => 'transports',
                'shopware.filesystem.private_allowed_extensions' => ['png'],
                'shopware.html_sanitizer.enabled' => true,
                'shopware.media.enable_url_upload_feature' => true,
                'shopware.staging.administration.show_banner' => false,
                'shopware.deployment.runtime_extension_management' => true,
            ]),
            $kernel,
            $this->createMock(BusinessEventCollector::class),
            static::getContainer()->get('shopware.increment.gateway.registry'),
            $this->connection,
            static::getContainer()->get(AppUrlVerifier::class),
            static::getContainer()->get('router'),
            $eventCollector,
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(ApiRouteInfoResolver::class),
            static::getContainer()->get(InAppPurchase::class),
            new ViteFileAccessorDecorator(
                [],
                static::getContainer()->get('shopware.asset.asset'),
                $kernel,
                new Filesystem(),
            ),
            new Filesystem(),
            static::getContainer()->get(ShopIdProvider::class),
            $this->createMock(StatsService::class),
        );

        $infoController->setContainer($this->createMock(Container::class));

        $content = $infoController->config(Context::createDefaultContext(), Request::create($appUrl))->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(4, $config['bundles']);

        static::assertArrayHasKey('AdminExtensionApiBundle', $config['bundles']);
        static::assertSame('https://extension-bundle.test', $config['bundles']['AdminExtensionApiBundle']['baseUrl']);
        static::assertSame('plugin', $config['bundles']['AdminExtensionApiBundle']['type']);

        static::assertArrayNotHasKey('AdminExtensionApiWithoutSelfKnownBaseUrlBundle', $config['bundles']);

        static::assertArrayHasKey('AdminExtensionApiPlugin', $config['bundles']);
        static::assertSame('https://extension-api.test', $config['bundles']['AdminExtensionApiPlugin']['baseUrl']);
        static::assertSame('plugin', $config['bundles']['AdminExtensionApiPlugin']['type']);

        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $config['bundles']);
        static::assertStringContainsString(
            '/admin/adminextensionapipluginwithlocalentrypoint/index.html',
            $config['bundles']['AdminExtensionApiPluginWithLocalEntryPoint']['baseUrl'],
        );
        static::assertSame('plugin', $config['bundles']['AdminExtensionApiPluginWithLocalEntryPoint']['type']);

        static::assertArrayHasKey('AdminExtensionApiApp', $config['bundles']);
        static::assertSame('https://app-admin.test', $config['bundles']['AdminExtensionApiApp']['baseUrl']);
        static::assertSame('app', $config['bundles']['AdminExtensionApiApp']['type']);
    }

    public function testFlowActionsRoute(): void
    {
        $url = '/api/_info/flow-actions.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'extensions' => [],
                'name' => 'action.add.order.tag',
                'requirements' => [
                    'orderAware',
                ],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, static fn ($x) => $x['name'] === $action['name']));
            static::assertNotEmpty($actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertSame($action, $actualActions[0]);
        }
    }

    public function testFlowActionRouteHasAppFlowActions(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $appId = Uuid::randomHex();
        $this->createApp($appId, $aclRoleId);

        $flowAppId = Uuid::randomHex();
        $this->createAppFlowAction($flowAppId, $appId);

        $url = '/api/_info/flow-actions.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $expected = [
            [
                'extensions' => [],
                'name' => 'telegram.send.message',
                'requirements' => [
                    'orderaware',
                ],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, static fn ($x) => $x['name'] === $action['name']));
            static::assertNotEmpty($actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertSame($action, $actualActions[0]);
        }
    }

    public function testMailAwareBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        foreach ($response as $event) {
            if (\in_array($event['name'], ['mail.after.create.message', 'mail.before.send', 'mail.sent'], true)) {
                static::assertNotContains(MailAware::class, $event['aware']);

                continue;
            }

            static::assertContains(MailAware::class, $event['aware'], $event['name']);
        }
    }

    public function testFlowBusinessEventRouteHasAppFlowEvents(): void
    {
        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $appId = Uuid::randomHex();
        $this->createApp($appId, $aclRoleId);

        $flowAppId = Uuid::randomHex();
        $this->createAppFlowEvent($flowAppId, $appId);

        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $expected = [
            [
                'extensions' => [],
                'name' => 'customer.wishlist',
                'class' => CustomAppEvent::class,
                'data' => [],
                'aware' => [
                    'mailAware',
                    'customerAware',
                ],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvent = array_values(array_filter($response, static function ($x) use ($event) {
                return $x['name'] === $event['name'];
            }));

            static::assertNotEmpty($actualEvent, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvent);
            static::assertSame($event, $actualEvent[0]);
        }
    }

    public function testFetchApiRoutes(): void
    {
        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/routes');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $routes = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        foreach ($routes['endpoints'] as $route) {
            static::assertArrayHasKey('path', $route);
            static::assertArrayHasKey('methods', $route);
        }
    }

    public function testFetchMessageStats(): void
    {
        $statsService = $this->getContainer()->get(StatsService::class);
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 2))),
        ]));
        $statsService->registerMessage(new Envelope(new \stdClass(), [
            new SentAtStamp(new \DateTimeImmutable('@' . (time() - 1))),
        ]));

        $client = $this->getBrowser();
        $client->request(Request::METHOD_GET, '/api/_info/message-stats.json');

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        static::assertJson($content);
        $stats = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($stats);
        static::assertArrayHasKey('enabled', $stats);
        static::assertTrue($stats['enabled']);
        static::assertArrayHasKey('stats', $stats);
        static::assertIsArray($stats['stats']);
        static::assertArrayHasKey('totalMessagesProcessed', $stats['stats']);
        static::assertGreaterThanOrEqual(2, $stats['stats']['totalMessagesProcessed']);
        static::assertArrayHasKey('processedSince', $stats['stats']);
        static::assertInstanceOf(\DateTimeInterface::class, \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339_EXTENDED, $stats['stats']['processedSince']));
        static::assertArrayHasKey('averageTimeInQueue', $stats['stats']);
        static::assertIsFloat($stats['stats']['averageTimeInQueue']);
        static::assertArrayHasKey('messageTypeStats', $stats['stats']);
        static::assertIsArray($stats['stats']['messageTypeStats']);
        static::assertArrayHasKey('type', $stats['stats']['messageTypeStats'][0]);
        static::assertSame('stdClass', $stats['stats']['messageTypeStats'][0]['type']);
        static::assertArrayHasKey('count', $stats['stats']['messageTypeStats'][0]);
    }

    private function createApp(string $appId, string $aclRoleId): void
    {
        $this->connection->insert('app', [
            'id' => Uuid::fromHexToBytes($appId),
            'name' => 'flowbuilderactionapp',
            'active' => 1,
            'path' => 'custom/apps/flowbuilderactionapp',
            'version' => '1.0.0',
            'configurable' => 0,
            'app_secret' => 'appSecret',
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'integration_id' => $this->getIntegrationId(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowAction(string $flowAppId, string $appId): void
    {
        $this->connection->insert('app_flow_action', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'telegram.send.message',
            'badge' => 'Telegram',
            'url' => 'https://example.xyz',
            'delayable' => true,
            'requirements' => json_encode(['orderaware'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowEvent(string $flowAppId, string $appId): void
    {
        $this->connection->insert('app_flow_event', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'customer.wishlist',
            'aware' => json_encode(['mailAware', 'customerAware'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getIntegrationId(): string
    {
        $integrationId = Uuid::randomBytes();

        $this->connection->insert('integration', [
            'id' => $integrationId,
            'access_key' => 'test',
            'secret_access_key' => 'test',
            'label' => 'test',
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $integrationId;
    }

    private function createAclRole(string $aclRoleId): void
    {
        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer'], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}

/**
 * @internal
 */
class AdminExtensionApiBundle extends Bundle
{
    public function getAdminBaseUrl(): ?string
    {
        return 'https://extension-bundle.test';
    }
}

/**
 * @internal
 */
class AdminExtensionApiWithoutSelfKnownBaseUrlBundle extends Bundle
{
}

/**
 * @internal
 */
class AdminExtensionApiPlugin extends Plugin
{
    public function getAdminBaseUrl(): ?string
    {
        return 'https://extension-api.test';
    }
}

/**
 * @internal
 */
class AdminExtensionApiPluginWithLocalEntryPoint extends Plugin
{
    public function getPath(): string
    {
        $reflected = new \ReflectionObject($this);

        return \dirname($reflected->getFileName() ?: '') . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint';
    }
}
