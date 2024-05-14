<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Update\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
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
#[CoversClass(UpdateController::class)]
class UpdateControllerTest extends TestCase
{
    public function testCheckForUpdatesNoUpdate(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.1.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.5.1.0'
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{}', $content);
    }

    public function testCheckForUpdatesWithUpdate(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{"extensions":[],"title":"","body":"","date":"2020-01-01T00:00:00.000+00:00","version":"6.5.0.0","fixedVulnerabilities":[]}', $content);
    }

    public function testCheckForUpdatesNoUpdateWithDisabledUpdateCheckByEnv(): void
    {
        $apiClient = $this->createMock(ApiClient::class);
        $apiClient
            ->method('checkForUpdates')
            ->willReturn(new Version(['version' => '6.5.0.0', 'date' => '2020-01-01']));

        $updateController = new UpdateController(
            $apiClient,
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0.0',
            true
        );

        $response = $updateController->updateApiCheck();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('{}', $content);
    }

    public function testCheckForRequirements(): void
    {
        $writeableCheck = $this->createMock(WriteableCheck::class);
        $writeableCheck
            ->method('check')
            ->willReturn(new ValidationResult('writeable', false, 'message'));

        $licenseCheck = $this->createMock(LicenseCheck::class);
        $licenseCheck
            ->method('check')
            ->willReturn(new ValidationResult('license', false, 'message'));

        $updateController = new UpdateController(
            $this->createMock(ApiClient::class),
            $writeableCheck,
            $licenseCheck,
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->checkRequirements();

        $content = $response->getContent();

        static::assertJson((string) $content);
        static::assertSame('[{"extensions":[],"name":"writeable","result":false,"message":"message","vars":[]},{"extensions":[],"name":"license","result":false,"message":"message","vars":[]}]', $content);
    }

    public function testCheckPluginCompatibility(): void
    {
        $pluginCompatibility = $this->createMock(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionCompatibilities')
            ->willReturn(['test' => true]);

        $updateController = new UpdateController(
            $this->createMock(ApiClient::class),
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $pluginCompatibility,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
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
        $apiClient->expects(static::once())->method('downloadRecoveryTool');

        $updateController = new UpdateController(
            $apiClient,
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $this->createMock(ExtensionCompatibility::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $response = $updateController->downloadLatestRecovery();

        static::assertInstanceOf(NoContentResponse::class, $response);
    }

    public function testDeactivateExtensions(): void
    {
        $events = [];

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$events): object {
                $events[] = $event;

                return $event;
            });

        $updateController = new UpdateController(
            $this->createMock(ApiClient::class),
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $this->createMock(ExtensionCompatibility::class),
            $eventDispatcher,
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $container = new ContainerBuilder();
        $service = $this->createMock(Kernel::class);
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

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$events): object {
                $events[] = $event;

                return $event;
            });

        $extension = new ExtensionStruct();
        $extension->setName('test');
        $extension->setType(ExtensionStruct::EXTENSION_TYPE_APP);

        $pluginCompatibility = $this->createMock(ExtensionCompatibility::class);
        $pluginCompatibility
            ->method('getExtensionsToDeactivate')
            ->willReturn([$extension, $extension]);

        $updateController = new UpdateController(
            $this->createMock(ApiClient::class),
            $this->createMock(WriteableCheck::class),
            $this->createMock(LicenseCheck::class),
            $pluginCompatibility,
            $eventDispatcher,
            $this->createMock(SystemConfigService::class),
            $this->createMock(AbstractExtensionLifecycle::class),
            '6.1.0'
        );

        $container = new ContainerBuilder();
        $service = $this->createMock(Kernel::class);
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
