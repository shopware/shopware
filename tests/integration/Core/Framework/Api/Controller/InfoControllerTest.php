<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use AppSystemTestBehaviour;

    public function testGetConfig(): void
    {
        $expected = [
            'version' => $this->getContainer()->getParameter('kernel.shopware_version'),
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_admin_worker'),
                'enableQueueStatsWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_queue_stats_worker'),
                'enableNotificationWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_notification_worker'),
                'transports' => $this->getContainer()->getParameter('shopware.admin_worker.transports'),
            ],
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => true,
                'appUrlReachable' => true,
                'appsRequireAppUrl' => false,
                'private_allowed_extensions' => $this->getContainer()->getParameter('shopware.filesystem.private_allowed_extensions'),
                'enableHtmlSanitizer' => $this->getContainer()->getParameter('shopware.html_sanitizer.enabled'),
                'enableStagingMode' => false,
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        // reset environment based miss match
        $decodedResponse['bundles'] = [];
        $decodedResponse['versionRevision'] = $expected['versionRevision'];

        static::assertEquals($decodedResponse, $expected);
    }

    public function testGetConfigWithPermissions(): void
    {
        $ids = new IdsCollection();
        $appRepository = $this->getContainer()->get('app.repository');
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
                'enableAdminWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->getContainer()->getParameter('shopware.admin_worker.transports'),
            ],
            'bundles' => $bundle,
            'settings' => [
                'enableUrlFeature' => true,
                'enableHtmlSanitizer' => $this->getContainer()->getParameter('shopware.html_sanitizer.enabled'),
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $client->getResponse()->getStatusCode());

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
            'version' => $this->getContainer()->getParameter('kernel.shopware_version'),
        ];

        $url = '/api/_info/version';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotEmpty($version);
        static::assertStringStartsWith($version, $content);
    }

    public function testGetShopwareVersionOldVersion(): void
    {
        $expected = [
            'version' => $this->getContainer()->getParameter('kernel.shopware_version'),
        ];

        $url = '/api/v1/_info/version';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());

        $version = mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3);
        static::assertNotEmpty($version);
        static::assertStringStartsWith($version, $content);
    }

    public function testBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'name' => 'checkout.customer.login',
                'class' => CustomerLoginEvent::class,
                'extensions' => [],
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
                'name' => 'checkout.order.placed',
                'class' => CheckoutOrderPlacedEvent::class,
                'extensions' => [],
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
                    MailAware::class,
                    lcfirst((new \ReflectionClass(MailAware::class))->getShortName()),
                    SalesChannelAware::class,
                    lcfirst((new \ReflectionClass(SalesChannelAware::class))->getShortName()),
                    OrderAware::class,
                    lcfirst((new \ReflectionClass(OrderAware::class))->getShortName()),
                ],
            ],
            [
                'name' => 'state_enter.order_delivery.state.shipped_partially',
                'class' => OrderStateMachineStateChangeEvent::class,
                'extensions' => [],
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
                ],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvents = array_values(array_filter($response, fn ($x) => $x['name'] === $event['name']));
            sort($event['aware']);
            sort($actualEvents[0]['aware']);
            static::assertNotEmpty($actualEvents, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvents);
            static::assertEquals($event, $actualEvents[0], $event['name']);
        }
    }

    public function testBundlePaths(): void
    {
        $kernelMock = $this->createMock(Kernel::class);
        $packagesMock = $this->createMock(Packages::class);
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
            ]),
            $kernelMock,
            $packagesMock,
            $this->createMock(BusinessEventCollector::class),
            $this->getContainer()->get('shopware.increment.gateway.registry'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppUrlVerifier::class),
            $this->getContainer()->get('router'),
            $eventCollector,
            $this->getContainer()->get(SystemConfigService::class),
        );

        $infoController->setContainer($this->createMock(Container::class));

        $assetPackage = $this->createMock(Package::class);
        $packagesMock
            ->expects(static::exactly(1))
            ->method('getPackage')
            ->willReturn($assetPackage);
        $assetPackage
            ->expects(static::exactly(1))
            ->method('getUrl')
            ->willReturnArgument(0);

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([new BundleFixture('SomeFunctionalityBundle', __DIR__ . '/Fixtures/InfoController')]);

        $content = $infoController->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('SomeFunctionalityBundle', $config['bundles']);

        $jsFilePath = explode('?', (string) $config['bundles']['SomeFunctionalityBundle']['js'][0])[0];
        static::assertEquals(
            'bundles/somefunctionality/administration/js/some-functionality-bundle.js',
            $jsFilePath
        );
    }

    public function testBundlePathsWithMarkerOnly(): void
    {
        $kernelMock = $this->createMock(Kernel::class);
        $packagesMock = $this->createMock(Packages::class);
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
                'shopware.staging.administration.show_banner' => false,
            ]),
            $kernelMock,
            $packagesMock,
            $this->createMock(BusinessEventCollector::class),
            $this->getContainer()->get('shopware.increment.gateway.registry'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppUrlVerifier::class),
            $this->getContainer()->get('router'),
            $eventCollector,
            $this->getContainer()->get(SystemConfigService::class),
        );

        $infoController->setContainer($this->createMock(Container::class));

        $assetPackage = $this->createMock(Package::class);
        $packagesMock
            ->expects(static::exactly(1))
            ->method('getPackage')
            ->willReturn($assetPackage);
        $assetPackage
            ->expects(static::exactly(1))
            ->method('getUrl')
            ->willReturnArgument(0);

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([new BundleFixture('SomeFunctionalityBundle', __DIR__ . '/Fixtures/InfoControllerWithMarker')]);

        $content = $infoController->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('SomeFunctionalityBundle', $config['bundles']);

        $jsFilePath = explode('?', (string) $config['bundles']['SomeFunctionalityBundle']['js'][0])[0];
        static::assertEquals(
            'bundles/somefunctionality/administration/js/some-functionality-bundle.js',
            $jsFilePath
        );
    }

    public function testBaseAdminPaths(): void
    {
        if (!class_exists(AdministrationController::class)) {
            static::markTestSkipped('Cannot test without Administration as results will differ');
        }

        $this->clearRequestStack();

        $this->loadAppsFromDir(__DIR__ . '/Fixtures/AdminExtensionApiApp');

        $kernelMock = $this->createMock(Kernel::class);
        $eventCollector = $this->createMock(FlowActionCollector::class);

        $basePath = new UrlPackage(['http://localhost'], new EmptyVersionStrategy());
        $assets = new Packages($basePath, ['asset' => $basePath]);

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
            ]),
            $kernelMock,
            $assets,
            $this->createMock(BusinessEventCollector::class),
            $this->getContainer()->get('shopware.increment.gateway.registry'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppUrlVerifier::class),
            $this->getContainer()->get('router'),
            $eventCollector,
            $this->getContainer()->get(SystemConfigService::class),
        );

        $infoController->setContainer($this->createMock(Container::class));

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([
                new AdminExtensionApiPlugin(true, __DIR__ . '/Fixtures/InfoController'),
                new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
            ]);

        $content = $infoController->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(3, $config['bundles']);

        static::assertArrayHasKey('AdminExtensionApiPlugin', $config['bundles']);
        static::assertEquals('https://extension-api.test', $config['bundles']['AdminExtensionApiPlugin']['baseUrl']);
        static::assertEquals('plugin', $config['bundles']['AdminExtensionApiPlugin']['type']);

        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $config['bundles']);
        static::assertEquals(
            'http://localhost:8000/admin/adminextensionapipluginwithlocalentrypoint/index.html',
            $config['bundles']['AdminExtensionApiPluginWithLocalEntryPoint']['baseUrl'],
        );
        static::assertEquals('plugin', $config['bundles']['AdminExtensionApiPluginWithLocalEntryPoint']['type']);

        static::assertArrayHasKey('AdminExtensionApiApp', $config['bundles']);
        static::assertEquals('https://app-admin.test', $config['bundles']['AdminExtensionApiApp']['baseUrl']);
        static::assertEquals('app', $config['bundles']['AdminExtensionApiApp']['type']);
    }

    public function testFlowActionsRoute(): void
    {
        $url = '/api/_info/flow-actions.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'name' => 'action.add.order.tag',
                'requirements' => [
                    'orderAware',
                ],
                'extensions' => [],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, fn ($x) => $x['name'] === $action['name']));
            static::assertNotEmpty($actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertEquals($action, $actualActions[0]);
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
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $expected = [
            [
                'name' => 'telegram.send.message',
                'requirements' => [
                    'orderaware',
                ],
                'extensions' => [],
                'delayable' => true,
            ],
        ];

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, fn ($x) => $x['name'] === $action['name']));
            static::assertNotEmpty($actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertEquals($action, $actualActions[0]);
        }
    }

    public function testMailAwareBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        foreach ($response as $event) {
            if ($event['name'] === 'mail.after.create.message' || $event['name'] === 'mail.before.send' || $event['name'] === 'mail.sent') {
                static::assertFalse(\in_array('Shopware\Core\Framework\Event\MailAware', $event['aware'], true));

                continue;
            }

            static::assertContains('Shopware\Core\Framework\Event\MailAware', $event['aware'], $event['name']);
            static::assertNotContains('Shopware\Core\Framework\Event\MailActionInterface', $event['aware'], $event['name']);
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
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true);

        $expected = [
            [
                'name' => 'customer.wishlist',
                'aware' => [
                    'mailAware',
                    'customerAware',
                ],
                'data' => [],
                'class' => 'Shopware\Core\Framework\App\Event\CustomAppEvent',
                'extensions' => [],
            ],
        ];

        foreach ($expected as $event) {
            $actualEvent = array_values(array_filter($response, function ($x) use ($event) {
                return $x['name'] === $event['name'];
            }));

            static::assertNotEmpty($actualEvent, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvent);
            static::assertEquals($event, $actualEvent[0]);
        }
    }

    private function createApp(string $appId, string $aclRoleId): void
    {
        $this->getContainer()->get(Connection::class)->insert('app', [
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
        $this->getContainer()->get(Connection::class)->insert('app_flow_action', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'telegram.send.message',
            'badge' => 'Telegram',
            'url' => 'https://example.xyz',
            'delayable' => true,
            'requirements' => json_encode(['orderaware']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAppFlowEvent(string $flowAppId, string $appId): void
    {
        $this->getContainer()->get(Connection::class)->insert('app_flow_event', [
            'id' => Uuid::fromHexToBytes($flowAppId),
            'app_id' => Uuid::fromHexToBytes($appId),
            'name' => 'customer.wishlist',
            'aware' => json_encode(['mailAware', 'customerAware']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getIntegrationId(): string
    {
        $integrationId = Uuid::randomBytes();

        $this->getContainer()->get(Connection::class)->insert('integration', [
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
        $this->getContainer()->get(Connection::class)->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
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
