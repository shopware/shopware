<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Framework\Update\UpdateException;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateController::class)]
class UpdateControllerTest extends TestCase
{
    public function testCheckForUpdatesNoUpdate(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.1.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.5.1.0'
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{}', $content);
    }

    public function testCheckForUpdatesWithUpdate(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"extensions":[],"title":"","body":"","date":"2020-01-01T00:00:00.000+00:00","version":"6.5.0.0","fixedVulnerabilities":[],"autoUpdateEnabled":true,"clusterSetup":false}', $content);
    }

    public function testCheckForUpdatesStillReportsUpdateWhenAutoUpdateIsDisabled(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0.0',
            shopwareUpdateEnabled: false,
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"extensions":[],"title":"","body":"","date":"2020-01-01T00:00:00.000+00:00","version":"6.5.0.0","fixedVulnerabilities":[],"autoUpdateEnabled":false,"clusterSetup":false}', $content);
    }

    public function testCheckLicenseIsValidWithoutLicenseHost(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn('');

        $response = $this->createController(systemConfig: $systemConfig)->checkLicense();

        static::assertJson((string) $response->getContent());
        static::assertSame('{"isValid":true}', $response->getContent());
    }

    public function testCheckLicenseIsValidWithUpgradeableShop(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn('licensehost.test');

        $storeClient = static::createStub(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(true);

        $response = $this->createController(storeClient: $storeClient, systemConfig: $systemConfig)->checkLicense();

        static::assertSame('{"isValid":true}', $response->getContent());
    }

    public function testCheckLicenseIsInvalid(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn('licensehost.test');

        $storeClient = static::createStub(StoreClient::class);
        $storeClient->method('isShopUpgradeable')->willReturn(false);

        $response = $this->createController(storeClient: $storeClient, systemConfig: $systemConfig)->checkLicense();

        static::assertSame('{"isValid":false}', $response->getContent());
    }

    public function testCheckPluginCompatibility(): void
    {
        $pluginCompatibility = static::createStub(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionCompatibilities')
            ->willReturn(['test' => true]);

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(StoreClient::class),
            $pluginCompatibility,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->extensionCompatibility(Context::createDefaultContext());

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"test":true}', $content);
    }

    public function testDownloadRecovery(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient->expects($this->once())->method('downloadRecoveryTool');

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->downloadLatestRecovery();

        static::assertInstanceOf(NoContentResponse::class, $response);
    }

    public function testDeactivateExtensions(): void
    {
        $events = [];

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$events): object {
                $events[] = $event;

                return $event;
            });

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            $eventDispatcher,
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $container = new ContainerBuilder();
        $service = static::createStub(Kernel::class);
        $service->method('getContainer')->willReturn($container);
        $container->set('kernel', $service);
        $container->set('event_dispatcher', $eventDispatcher);

        $updateController->setContainer($container);

        $updateController->deactivateExtensions(new Request(), Context::createDefaultContext());

        static::assertCount(2, $events);
        static::assertArrayHasKey(0, $events);
        static::assertInstanceOf(UpdatePrePrepareEvent::class, $events[0]);
        static::assertArrayHasKey(1, $events);
        static::assertInstanceOf(UpdatePostPrepareEvent::class, $events[1]);
    }

    public function testDeactivateMultipleExtensions(): void
    {
        $events = [];

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$events): object {
                $events[] = $event;

                return $event;
            });

        $extension = new ExtensionStruct();
        $extension->setName('test');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = static::createStub(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension, $extension]);

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(StoreClient::class),
            $pluginCompatibility,
            $eventDispatcher,
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $container = new ContainerBuilder();
        $service = static::createStub(Kernel::class);
        $service->method('getContainer')->willReturn($container);
        $container->set('kernel', $service);
        $container->set('event_dispatcher', $eventDispatcher);

        $updateController->setContainer($container);

        $response = $updateController->deactivateExtensions(new Request(), Context::createDefaultContext());

        static::assertCount(1, $events);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(
            [
                'offset' => 1,
                'total' => 3,
            ],
            $content
        );
    }

    public function testCheckForUpdatesReportsClusterSetup(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0.0',
            clusterSetup: true,
        );

        $content = $updateController->updateApiCheck()->getContent();

        static::assertIsString($content);
        static::assertStringContainsString('"clusterSetup":true', $content);
    }

    public function testDownloadRecoveryThrowsOnClusterSetup(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient->expects($this->never())->method('downloadRecoveryTool');

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0',
            clusterSetup: true,
        );

        $this->expectExceptionObject(UpdateException::clusterSetupNotSupported());

        $updateController->downloadLatestRecovery();
    }

    public function testDownloadRecoveryThrowsWhenAutoUpdateIsDisabled(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient->expects($this->never())->method('downloadRecoveryTool');

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0',
            shopwareUpdateEnabled: false,
        );

        $this->expectExceptionObject(UpdateException::autoUpdateDisabled());

        $updateController->downloadLatestRecovery();
    }

    public function testDeactivateExtensionsThrowsWhenAutoUpdateIsDisabled(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient->expects($this->never())->method('checkForUpdates');

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0',
            shopwareUpdateEnabled: false,
        );

        $this->expectExceptionObject(UpdateException::autoUpdateDisabled());

        $updateController->deactivateExtensions(new Request(), Context::createDefaultContext());
    }

    public function testCheckLicenseWorksWhenAutoUpdateIsDisabled(): void
    {
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn('');

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            $systemConfig,
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0',
            shopwareUpdateEnabled: false,
        );

        static::assertSame('{"isValid":true}', (string) $updateController->checkLicense()->getContent());
    }

    public function testAllEndpointsThrowWhenModuleIsHidden(): void
    {
        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0',
            updateModuleHidden: true,
        );

        $endpoints = [
            'update check' => static fn () => $updateController->updateApiCheck(),
            'check license' => static fn () => $updateController->checkLicense(),
            'extension compatibility' => static fn () => $updateController->extensionCompatibility(Context::createDefaultContext()),
            'download recovery' => static fn () => $updateController->downloadLatestRecovery(),
            'deactivate extensions' => static fn () => $updateController->deactivateExtensions(new Request(), Context::createDefaultContext()),
        ];

        foreach ($endpoints as $name => $endpoint) {
            try {
                $endpoint();
                static::fail(\sprintf('Expected UpdateException for endpoint "%s"', $name));
            } catch (UpdateException $e) {
                static::assertSame(UpdateException::UPDATE_MODULE_HIDDEN, $e->getErrorCode(), $name);
            }
        }
    }

    private function createController(?StoreClient $storeClient = null, ?SystemConfigService $systemConfig = null): UpdateController
    {
        return new UpdateController(
            static::createStub(ApiClient::class),
            $storeClient ?? static::createStub(StoreClient::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            $systemConfig ?? static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );
    }
}
