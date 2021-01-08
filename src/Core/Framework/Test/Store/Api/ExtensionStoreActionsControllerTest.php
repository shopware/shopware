<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtensionStoreActionsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        parent::setUp();
    }

    public function testRefreshExtensions(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $pluginService = $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $pluginService->expects(static::once())->method('refreshPlugins');

        static::assertInstanceOf(Response::class, $controller->refreshExtensions(Context::createDefaultContext()));
    }

    public function testUploadExtensionsWithInvalidFile(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('foo');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), __METHOD__));
        $request->files->set('file', $file);

        static::expectException(PluginNotAZipFileException::class);
        $controller->uploadExtensions($request, Context::createDefaultContext());
    }

    public function testUploadExtensionsWithUnpackError(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $pluginManagement = $this->createMock(PluginManagementService::class)
        );

        $pluginManagement->method('uploadPlugin')->willThrowException(new \RuntimeException('Error'));

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/zip');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), __METHOD__));
        $request->files->set('file', $file);

        static::expectException(\RuntimeException::class);
        $controller->uploadExtensions($request, Context::createDefaultContext());
    }

    public function testUploadExtensions(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/zip');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), __METHOD__));
        $request->files->set('file', $file);

        $response = $controller->uploadExtensions($request, Context::createDefaultContext());

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testDownloadExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $downloader = $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $downloader->expects(static::once())->method('download');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->downloadExtension('test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testInstallExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('install');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->installExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testUninstallExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('uninstall');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->uninstallExtension('plugin', 'test', new Request(), Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testRemoveExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('remove');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->removeExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testActivateExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('activate');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->activateExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testDeactivateExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('deactivate');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->deactivateExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testUpdateExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecyle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class)
        );

        $lifecyle->expects(static::once())->method('update');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->updateExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }
}
