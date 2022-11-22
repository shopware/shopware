<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Enqueue\Container\Container;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Flow\Dispatching\Aware\ContextTokenAware;
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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->getContainer()->getParameter('shopware.admin_worker.transports'),
            ],
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => true,
                'appUrlReachable' => true,
                'appsRequireAppUrl' => false,
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        foreach (array_keys($expected) as $key) {
            static::assertArrayHasKey($key, $decodedResponse);
        }

        static::assertEquals($expected['settings'], $decodedResponse['settings']);

        unset($expected['settings']);
        static::assertStringStartsWith(mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3), $content);
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
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $decodedResponse = json_decode($content, true);

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
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
        ];

        $url = '/api/_info/version';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertStringStartsWith(mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3), $content);
    }

    public function testGetShopwareVersionOldVersion(): void
    {
        $expected = [
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
        ];

        $url = '/api/v1/_info/version';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertStringStartsWith(mb_substr(json_encode($expected, \JSON_THROW_ON_ERROR), 0, -3), $content);
    }

    public function testBusinessEventRoute(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        static::assertJson($content);

        $response = json_decode($content, true);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'name' => 'checkout.customer.login',
                'class' => "Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent",
                'mailAware' => true,
                'logAware' => false,
                'salesChannelAware' => true,
                'extensions' => [],
                'data' => [
                    'customer' => [
                        'type' => 'entity',
                        'entityClass' => CustomerDefinition::class,
                    ],
                    'contextToken' => [
                        'type' => 'string',
                    ],
                ],
                'aware' => [
                    ContextTokenAware::class,
                    lcfirst((new \ReflectionClass(ContextTokenAware::class))->getShortName()),
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
                'class' => "Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent",
                'mailAware' => true,
                'logAware' => false,
                'salesChannelAware' => true,
                'extensions' => [],
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
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
                'class' => "Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent",
                'mailAware' => true,
                'logAware' => false,
                'salesChannelAware' => true,
                'extensions' => [],
                'data' => [
                    'order' => [
                        'type' => 'entity',
                        'entityClass' => OrderDefinition::class,
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
            $actualEvents = array_values(array_filter($response, function ($x) use ($event) {
                return $x['name'] === $event['name'];
            }));
            sort($event['aware']);
            sort($actualEvents[0]['aware']);
            static::assertNotEmpty($actualEvents, 'Event with name "' . $event['name'] . '" not found');
            static::assertCount(1, $actualEvents);
            static::assertEquals($event, $actualEvents[0]);
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
                'shopware.admin_worker.transports' => 'transports',
            ]),
            $kernelMock,
            $packagesMock,
            $this->createMock(BusinessEventCollector::class),
            $this->getContainer()->get('shopware.increment.gateway.registry'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppUrlVerifier::class),
            $eventCollector,
            true,
            []
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

        $content = $infoController->config(Context::createDefaultContext())->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true);
        static::assertArrayHasKey('SomeFunctionalityBundle', $config['bundles']);

        $jsFilePath = explode('?', $config['bundles']['SomeFunctionalityBundle']['js'][0])[0];
        static::assertEquals(
            'bundles/somefunctionality/administration/js/some-functionality-bundle.js',
            $jsFilePath
        );
    }

    public function testBaseAdminPaths(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/Fixtures/AdminExtensionApiApp');

        $kernelMock = $this->createMock(Kernel::class);
        $eventCollector = $this->createMock(FlowActionCollector::class);
        $infoController = new InfoController(
            $this->createMock(DefinitionService::class),
            new ParameterBag([
                'kernel.shopware_version' => 'shopware-version',
                'kernel.shopware_version_revision' => 'shopware-version-revision',
                'shopware.admin_worker.enable_admin_worker' => 'enable-admin-worker',
                'shopware.admin_worker.transports' => 'transports',
            ]),
            $kernelMock,
            $this->getContainer()->get('assets.packages'),
            $this->createMock(BusinessEventCollector::class),
            $this->getContainer()->get('shopware.increment.gateway.registry'),
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(AppUrlVerifier::class),
            $eventCollector,
            true,
            []
        );

        $infoController->setContainer($this->createMock(Container::class));

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([
                new AdminExtensionApiPlugin(true, __DIR__ . '/Fixtures/InfoController'),
                new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
            ]);

        $content = $infoController->config(Context::createDefaultContext())->getContent();
        static::assertNotFalse($content);
        $config = json_decode($content, true);
        static::assertCount(3, $config['bundles']);

        static::assertArrayHasKey('AdminExtensionApiPlugin', $config['bundles']);
        static::assertEquals('https://extension-api.test', $config['bundles']['AdminExtensionApiPlugin']['baseUrl']);
        static::assertEquals('plugin', $config['bundles']['AdminExtensionApiPlugin']['type']);

        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $config['bundles']);
        static::assertEquals(
            EnvironmentHelper::getVariable('APP_URL') . '/bundles/adminextensionapipluginwithlocalentrypoint/administration/index.html',
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

        $response = json_decode($content, true);

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

        if (!Feature::isActive('v6.5.0.0')) {
            $expected[0]['requirements'] = [
                "Shopware\Core\Framework\Event\OrderAware",
                'orderAware',
            ];
        }

        foreach ($expected as $action) {
            $actualActions = array_values(array_filter($response, function ($x) use ($action) {
                return $x['name'] === $action['name'];
            }));
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

        $response = json_decode($content, true);

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
            $actualActions = array_values(array_filter($response, function ($x) use ($action) {
                return $x['name'] === $action['name'];
            }));
            static::assertNotEmpty($actualActions, 'Event with name "' . $action['name'] . '" not found');
            static::assertCount(1, $actualActions);
            static::assertEquals($action, $actualActions[0]);
        }
    }

    public function testMailAwareBusinessEventRoute(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
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
                static::assertFalse($event['mailAware']);
                static::assertFalse(\in_array('Shopware\Core\Framework\Event\MailAware', $event['aware'], true));

                continue;
            }
            static::assertTrue($event['mailAware']);
            static::assertTrue(\in_array('Shopware\Core\Framework\Event\MailAware', $event['aware'], true));
            static::assertFalse(\in_array('Shopware\Core\Framework\Event\MailActionInterface', $event['aware'], true));
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
