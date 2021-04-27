<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Composer\IO\NullIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Routing\Annotation\Acl;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 * @Acl({"system.plugin_maintain"})
 */
class ExtensionStoreActionsController extends AbstractController
{
    private AbstractExtensionLifecycle $extensionLifecycleService;

    private ExtensionDownloader $extensionDownloader;

    private PluginService $pluginService;

    private PluginManagementService $pluginManagementService;

    public function __construct(
        AbstractExtensionLifecycle $extensionLifecycleService,
        ExtensionDownloader $extensionDownloader,
        PluginService $pluginService,
        PluginManagementService $pluginManagementService
    ) {
        $this->extensionLifecycleService = $extensionLifecycleService;
        $this->extensionDownloader = $extensionDownloader;
        $this->pluginService = $pluginService;
        $this->pluginManagementService = $pluginManagementService;
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/refresh", name="api.extension.refresh", methods={"POST"})
     */
    public function refreshExtensions(Context $context): Response
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/upload", name="api.extension.upload", methods={"POST"})
     */
    public function uploadExtensions(Request $request, Context $context): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if ($file->getMimeType() !== 'application/zip') {
            unlink($file->getPathname());

            throw new PluginNotAZipFileException((string) $file->getMimeType());
        }

        try {
            $this->pluginManagementService->uploadPlugin($file, $context);
        } catch (\Exception $e) {
            unlink($file->getPathname());

            throw $e;
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/download/{technicalName}", name="api.extension.download", methods={"POST"})
     */
    public function downloadExtension(string $technicalName, Context $context): Response
    {
        $this->extensionDownloader->download($technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/install/{type}/{technicalName}", name="api.extension.install", methods={"POST"})
     */
    public function installExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->extensionLifecycleService->install($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/uninstall/{type}/{technicalName}", name="api.extension.uninstall", methods={"POST"})
     */
    public function uninstallExtension(string $type, string $technicalName, Request $request, Context $context): Response
    {
        $this->extensionLifecycleService->uninstall(
            $type,
            $technicalName,
            $request->request->getBoolean('keepUserData'),
            $context
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/remove/{type}/{technicalName}", name="api.extension.remove", methods={"DELETE"})
     */
    public function removeExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->extensionLifecycleService->remove($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/activate/{type}/{technicalName}", name="api.extension.activate", methods={"PUT"})
     */
    public function activateExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->extensionLifecycleService->activate($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/deactivate/{type}/{technicalName}", name="api.extension.deactivate", methods={"PUT"})
     */
    public function deactivateExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->extensionLifecycleService->deactivate($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Since("6.4.0.0")
     * @Route("/api/_action/extension/update/{type}/{technicalName}", name="api.extension.update", methods={"POST"})
     */
    public function updateExtension(string $type, string $technicalName, Context $context): Response
    {
        $this->extensionLifecycleService->update($type, $technicalName, $context);

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
