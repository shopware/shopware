<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Composer\IO\NullIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api'], '_acl' => ['system.plugin_maintain']])]
#[Package('checkout')]
class ExtensionStoreActionsController extends AbstractController
{
    public function __construct(
        private readonly AbstractExtensionLifecycle $extensionLifecycleService,
        private readonly ExtensionDownloader $extensionDownloader,
        private readonly PluginService $pluginService,
        private readonly PluginManagementService $pluginManagementService,
        private readonly Filesystem $fileSystem,
        private readonly bool $runtimeExtensionManagementAllowed
    ) {
    }

    #[Route(path: '/api/_action/extension/refresh', name: 'api.extension.refresh', methods: ['POST'])]
    public function refreshExtensions(Context $context): Response
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/upload', name: 'api.extension.upload', defaults: ['_acl' => ['system.plugin_upload']], methods: ['POST'])]
    public function uploadExtensions(Request $request, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            throw RoutingException::missingRequestParameter('file');
        }

        if ($file->getPathname() === '') {
            throw StoreException::couldNotUploadExtensionCorrectly();
        }

        if ($file->getMimeType() !== 'application/zip') {
            try {
                $this->fileSystem->remove($file->getPathname());
            } catch (\Throwable $e) {
                // Do nothing because the tmp file is already deleted by os
            }

            throw new PluginNotAZipFileException((string) $file->getMimeType());
        }

        try {
            $this->pluginManagementService->uploadPlugin($file, $context);
        } catch (\Exception $e) {
            try {
                $this->fileSystem->remove($file->getPathname());
            } catch (\Throwable $e) {
                // Do nothing because the tmp file is already deleted by os
            }

            throw $e;
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/download/{technicalName}', name: 'api.extension.download', methods: ['POST'])]
    public function downloadExtension(string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionDownloader->download($technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/install/{type}/{technicalName}', name: 'api.extension.install', methods: ['POST'])]
    public function installExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionLifecycleService->install($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/uninstall/{type}/{technicalName}', name: 'api.extension.uninstall', methods: ['POST'])]
    public function uninstallExtension(string $type, string $technicalName, Request $request, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionLifecycleService->uninstall(
            $type,
            $technicalName,
            $request->request->getBoolean('keepUserData'),
            $context
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/remove/{type}/{technicalName}', name: 'api.extension.remove', methods: ['DELETE'])]
    public function removeExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionLifecycleService->remove($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/activate/{type}/{technicalName}', name: 'api.extension.activate', methods: ['PUT'])]
    public function activateExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionLifecycleService->activate($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/deactivate/{type}/{technicalName}', name: 'api.extension.deactivate', methods: ['PUT'])]
    public function deactivateExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $this->extensionLifecycleService->deactivate($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/api/_action/extension/update/{type}/{technicalName}', name: 'api.extension.update', methods: ['POST'])]
    public function updateExtension(Request $request, string $type, string $technicalName, Context $context): Response
    {
        $this->checkExtensionManagementAllowed();

        $allowNewPermissions = $request->request->getBoolean('allowNewPermissions');

        $this->extensionLifecycleService->update($type, $technicalName, $allowNewPermissions, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function checkExtensionManagementAllowed(): void
    {
        if (!$this->runtimeExtensionManagementAllowed) {
            throw StoreException::extensionRuntimeExtensionManagementNotAllowed();
        }
    }
}
