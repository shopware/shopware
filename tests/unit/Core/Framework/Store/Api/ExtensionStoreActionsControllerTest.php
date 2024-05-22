<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ExtensionStoreActionsController::class)]
#[Package('checkout')]
class ExtensionStoreActionsControllerTest extends TestCase
{
    public function testRefreshExtensions(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $pluginService = $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $pluginService->expects(static::once())->method('refreshPlugins');

        $controller->refreshExtensions(Context::createDefaultContext());
    }

    public function testUploadExtensionsWithInvalidFile(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(true),
            true
        );

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('foo');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), __METHOD__));
        $request->files->set('file', $file);

        static::expectException(PluginNotAZipFileException::class);
        $controller->uploadExtensions($request, Context::createDefaultContext());
    }

    public function testUploadExtensionsWithInvalidFileAndDeleteFileException(): void
    {
        $fileSystemMock = $this->createFileSystemMock();
        if (!$fileSystemMock instanceof MockObject) {
            static::fail('Filesystem mock is not a mock object');
        }

        $fileSystemMock->expects(static::once())
            ->method('remove')
            ->willThrowException(new \RuntimeException('Error'));

        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $fileSystemMock,
            true
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
            $pluginManagement = $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(true),
            true
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
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/zip');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), __METHOD__));
        $request->files->set('file', $file);

        $response = $controller->uploadExtensions($request, Context::createDefaultContext());

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUploadExtensionsShallThrowExceptionIfPathToFileIsEmpty(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $request = new Request();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getPathname')->willReturn('');
        $request->files->set('file', $file);

        $this->expectException(StoreException::class);
        $controller->uploadExtensions($request, Context::createDefaultContext());
    }

    public function testDownloadExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $downloader = $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
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
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('install');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->installExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testUninstallExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('uninstall');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->uninstallExtension('plugin', 'test', new Request(), Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testRemoveExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('remove');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->removeExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testActivateExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('activate');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->activateExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testDeactivateExtension(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('deactivate');

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->deactivateExtension('plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testUpdateExtensionWithConsent(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('update');

        $request = new Request([], ['allowNewPermissions' => true]);

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->updateExtension($request, 'plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testUpdateExtensionWithoutConsent(): void
    {
        $controller = new ExtensionStoreActionsController(
            $lifecycle = $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            true
        );

        $lifecycle->expects(static::once())->method('update');

        $request = new Request([], ['allowNewPermissions' => false]);

        static::assertEquals(
            Response::HTTP_NO_CONTENT,
            $controller->updateExtension($request, 'plugin', 'test', Context::createDefaultContext())->getStatusCode()
        );
    }

    public function testApiIsBlockedWhenNoManagement(): void
    {
        $controller = new ExtensionStoreActionsController(
            $this->createMock(ExtensionLifecycleService::class),
            $this->createMock(ExtensionDownloader::class),
            $this->createMock(PluginService::class),
            $this->createMock(PluginManagementService::class),
            $this->createFileSystemMock(),
            false,
        );

        $context = Context::createDefaultContext();

        try {
            $controller->deactivateExtension('plugin', 'test', $context);
        } catch (StoreException $e) {
            static::assertEquals(StoreException::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED, $e->getErrorCode());
        }

        try {
            $controller->activateExtension('plugin', 'test', $context);
        } catch (StoreException $e) {
            static::assertEquals(StoreException::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED, $e->getErrorCode());
        }

        try {
            $controller->removeExtension('plugin', 'test', $context);
        } catch (StoreException $e) {
            static::assertEquals(StoreException::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED, $e->getErrorCode());
        }

        try {
            $controller->installExtension('plugin', 'test', $context);
        } catch (StoreException $e) {
            static::assertEquals(StoreException::EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED, $e->getErrorCode());
        }
    }

    private function createFileSystemMock(?bool $expectCallRemove = false): Filesystem
    {
        $fileSystem = $this->createMock(Filesystem::class);

        if ($expectCallRemove) {
            $fileSystem->expects(static::once())->method('remove');
        }

        return $fileSystem;
    }
}
