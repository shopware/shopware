<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    use EnvTestBehaviour;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventDispatcher = new EventDispatcher();
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
        static::assertSame('6.6.9999999.9999999-dev', $data['version']);
        static::assertArrayHasKey('versionRevision', $data);
        static::assertSame('PHPUnit', $data['versionRevision']);
        static::assertArrayHasKey('adminWorker', $data);

        $workerConfig = $data['adminWorker'];
        static::assertArrayHasKey('enableAdminWorker', $workerConfig);
        static::assertTrue($workerConfig['enableAdminWorker']);
        static::assertArrayHasKey('enableQueueStatsWorker', $workerConfig);
        static::assertTrue($workerConfig['enableQueueStatsWorker']);
        static::assertArrayHasKey('enableNotificationWorker', $workerConfig);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertArrayHasKey('transports', $workerConfig);
        static::assertIsArray($workerConfig['transports']);
        static::assertCount(1, $workerConfig['transports']);
        static::assertSame('slow', $workerConfig['transports'][0]);

        static::assertArrayHasKey('bundles', $data);
        $bundles = $data['bundles'];
        static::assertIsArray($bundles);
        static::assertCount(1, $bundles);
        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $bundles);
        $bundle = $bundles['AdminExtensionApiPluginWithLocalEntryPoint'];
        static::assertIsArray($bundle);
        static::assertArrayHasKey('css', $bundle);
        static::assertIsArray($bundle['css']);
        static::assertCount(0, $bundle['css']);
        static::assertArrayHasKey('js', $bundle);
        static::assertIsArray($bundle['js']);
        static::assertCount(0, $bundle['js']);
        static::assertArrayHasKey('baseUrl', $bundle);
        static::assertSame('/admin/adminextensionapipluginwithlocalentrypoint/index.html', $bundle['baseUrl']);
        static::assertArrayHasKey('type', $bundle);
        static::assertSame('plugin', $bundle['type']);

        static::assertArrayHasKey('settings', $data);
        $settings = $data['settings'];
        static::assertIsArray($settings);
        static::assertArrayHasKey('enableUrlFeature', $settings);
        static::assertTrue($settings['enableUrlFeature']);
        static::assertArrayHasKey('appUrlReachable', $settings);
        static::assertFalse($settings['appUrlReachable']);
        static::assertArrayHasKey('appsRequireAppUrl', $settings);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertArrayHasKey('private_allowed_extensions', $settings);
        static::assertFalse($settings['private_allowed_extensions']);
        static::assertArrayHasKey('enableHtmlSanitizer', $settings);
        static::assertTrue($settings['enableHtmlSanitizer']);

        static::assertArrayHasKey('inAppPurchases', $data);
        $inAppPurchases = $data['inAppPurchases'];
        static::assertIsArray($inAppPurchases);
        static::assertCount(1, $inAppPurchases);
        static::assertArrayHasKey('SwagApp', $inAppPurchases);
        static::assertSame(['SwagApp_premium'], $inAppPurchases['SwagApp']);
    }

    public function testConfigExtension(): void
    {
        $this->eventDispatcher->addListener(AdminInfoConfigEvent::class, function (AdminInfoConfigEvent $event): void {
            $event->addConfig('foo', 'bar');
        });

        $content = $this->createController()->config(Context::createDefaultContext(), Request::create('http://localhost'))->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey('foo', $data);
        static::assertSame('bar', $data['foo']);
    }

    private function createController(): InfoController
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

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->method('generate')
            ->with('administration.plugin.index', [
                'pluginName' => 'adminextensionapipluginwithlocalentrypoint',
            ])
            ->willReturn('/admin/adminextensionapipluginwithlocalentrypoint/index.html');

        $fileSystemOperator = $this->createMock(FilesystemOperator::class);
        $fileSystemOperator->method('fileExists')->willReturn(true);

        return new InfoController(
            $this->createMock(DefinitionService::class),
            $parameterBag,
            $kernel,
            $this->createMock(Packages::class),
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(IncrementGatewayRegistry::class),
            $this->createMock(Connection::class),
            $this->createMock(AppUrlVerifier::class),
            $routerMock,
            $this->createMock(FlowActionCollector::class),
            new StaticSystemConfigService(),
            $this->createMock(ApiRouteInfoResolver::class),
            StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]),
            $fileSystemOperator,
            $this->eventDispatcher,
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
