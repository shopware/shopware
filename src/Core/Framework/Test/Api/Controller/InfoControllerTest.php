<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Enqueue\Container\Container;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
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
use Shopware\Core\Framework\Test\Adapter\Twig\fixtures\BundleFixture;
use Shopware\Core\Framework\Test\Api\Controller\fixtures\AdminExtensionApiPlugin;
use Shopware\Core\Framework\Test\Api\Controller\fixtures\AdminExtensionApiPluginWithLocalEntryPoint\AdminExtensionApiPluginWithLocalEntryPoint;
use Shopware\Core\Framework\Test\App\AppSystemTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
            ],
        ];

        $url = '/api/_info/config';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertJson($client->getResponse()->getContent());

        $decodedResponse = json_decode($client->getResponse()->getContent(), true);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertSame(array_keys($expected), array_keys($decodedResponse));

        unset($expected['settings']);
        static::assertStringStartsWith(mb_substr(json_encode($expected), 0, -3), $client->getResponse()->getContent());
    }

    public function testGetConfigWithPermissions(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_17950')) {
            static::markTestSkipped('Only available with flag FEATURE_NEXT_17950');
        }
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
                    ],
                ],
                'baseAppUrl' => 'https://example.com',
            ],
        ], Context::createDefaultContext());

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $bundle = [
            'type' => 'app',
            'baseUrl' => 'https://example.com',
            'permissions' => [
                'create' => ['user'],
                'read' => ['user'],
                'update' => ['user'],
                'delete' => ['user'],
            ],
            'version' => '1.0.0',
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

        static::assertJson($client->getResponse()->getContent());

        $decodedResponse = json_decode($client->getResponse()->getContent(), true);
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertSame(array_keys($expected), array_keys($decodedResponse));

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

        static::assertJson($client->getResponse()->getContent());
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertStringStartsWith(mb_substr(json_encode($expected), 0, -3), $client->getResponse()->getContent());
    }

    public function testGetShopwareVersionOldVersion(): void
    {
        $expected = [
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
        ];

        $url = '/api/v1/_info/version';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertJson($client->getResponse()->getContent());
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertStringStartsWith(mb_substr(json_encode($expected), 0, -3), $client->getResponse()->getContent());
    }

    public function testBusinessEventRoute(): void
    {
        $url = '/api/_info/events.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'name' => 'checkout.customer.login',
                'class' => "Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent",
                'mailAware' => false,
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
                    SalesChannelAware::class,
                    CustomerAware::class,
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
                    MailAware::class,
                    SalesChannelAware::class,
                    OrderAware::class,
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
                    SalesChannelAware::class,
                    OrderAware::class,
                    CustomerAware::class,
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
            $this->getContainer()->get('app.repository'),
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
            ->willReturn([new BundleFixture('SomeFunctionalityBundle', __DIR__ . '/fixtures/InfoController')]);

        $config = json_decode($infoController->config(Context::createDefaultContext())->getContent(), true);
        static::assertArrayHasKey('SomeFunctionalityBundle', $config['bundles']);

        $jsFilePath = explode('?', $config['bundles']['SomeFunctionalityBundle']['js'][0])[0];
        static::assertEquals(
            'bundles/somefunctionality/administration/js/some-functionality-bundle.js',
            $jsFilePath
        );
    }

    public function testBaseAdminPaths(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_17950', $this);

        $this->loadAppsFromDir(__DIR__ . '/fixtures/AdminExtensionApiApp');

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
            $this->getContainer()->get('app.repository'),
            $eventCollector,
            true,
            []
        );

        $infoController->setContainer($this->createMock(Container::class));

        $kernelMock
            ->expects(static::exactly(1))
            ->method('getBundles')
            ->willReturn([
                new AdminExtensionApiPlugin(true, __DIR__ . '/fixtures/InfoController'),
                new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
            ]);

        $config = json_decode($infoController->config(Context::createDefaultContext())->getContent(), true);
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

        static::assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $expected = [
            [
                'name' => 'action.add.order.tag',
                'requirements' => [
                    "Shopware\Core\Framework\Event\OrderAware",
                ],
                'extensions' => [],
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
}
