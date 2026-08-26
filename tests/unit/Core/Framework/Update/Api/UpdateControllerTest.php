<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Checkers\LicenseCheck;
use Shopware\Core\Framework\Update\Checkers\WriteableCheck;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\ValidationResult;
use Shopware\Core\Framework\Update\Struct\Version;
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
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
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
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"extensions":[],"title":"","body":"","date":"2020-01-01T00:00:00.000+00:00","version":"6.5.0.0","fixedVulnerabilities":[]}', $content);
    }

    public function testCheckForUpdatesNoUpdateWithDisabledUpdateCheckByEnv(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0.0',
            true
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"disabled":true}', $content);
    }

    public function testCheckForUpdatesDisabledByAutoUpdateConfig(): void
    {
        $apiClient = static::createStub(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0.0',
            disableUpdateCheck: false,
            shopwareUpdateEnabled: false,
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"disabled":true}', $content);
    }

    public function testCheckForRequirements(): void
    {
        $writeableCheck = static::createStub(WriteableCheck::class);
        $writeableCheck
            ->method('check')
            ->willReturn(new ValidationResult('writeable', false, 'message'));

        $licenseCheck = static::createStub(LicenseCheck::class);
        $licenseCheck
            ->method('check')
            ->willReturn(new ValidationResult('license', false, 'message'));

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            $writeableCheck,
            $licenseCheck,
            static::createStub(ExtensionCompatibility::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->checkRequirements();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('[{"extensions":[],"name":"writeable","result":false,"message":"message","vars":[]},{"extensions":[],"name":"license","result":false,"message":"message","vars":[]}]', $content);
    }

    public function testCheckPluginCompatibility(): void
    {
        $pluginCompatibility = static::createStub(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionCompatibilities')
            ->willReturn(['test' => true]);

        $updateController = new UpdateController(
            static::createStub(ApiClient::class),
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
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
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
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
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
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

        $updateController->deactivatePlugins(new Request(), Context::createDefaultContext());

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
            static::createStub(WriteableCheck::class),
            static::createStub(LicenseCheck::class),
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

        $response = $updateController->deactivatePlugins(new Request(), Context::createDefaultContext());

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
}
