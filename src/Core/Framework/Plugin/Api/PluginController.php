<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Api;

use Composer\IO\NullIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\CanNotDeletePluginManagedByComposerException;
use Shopware\Core\Framework\Plugin\Exception\PluginCannotBeDeletedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PluginController extends AbstractController
{
    private const LAST_UPDATES_DAYS = 7;

    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    public function __construct(
        PluginService $pluginService,
        PluginLifecycleService $pluginLifecycleService,
        PluginManagementService $pluginManagementService
    ) {
        $this->pluginService = $pluginService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->pluginManagementService = $pluginManagementService;
    }

    /**
     * @Route("/api/v{version}/_action/plugin/upload", name="api.action.plugin.upload", methods={"POST"})
     */
    public function uploadPlugin(Request $request, Context $context): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if ($file->getMimeType() !== 'application/zip') {
            unlink($file->getPathname());
            throw new PluginNotAZipFileException($file->getMimeType());
        }

        try {
            $this->pluginManagementService->uploadPlugin($file);
        } catch (\Exception $e) {
            unlink($file->getPathname());
            throw $e;
        }
        $this->pluginService->refreshPlugins($context, new NullIO());

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/plugin/delete", name="api.action.plugin.delete", methods={"POST"})
     */
    public function deletePlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        if ($plugin->getInstalledAt() !== null) {
            throw new PluginCannotBeDeletedException('can not delete installed plugins');
        }

        if ($plugin->getManagedByComposer()) {
            throw new CanNotDeletePluginManagedByComposerException('can not delete plugins managed by composer');
        }

        $this->pluginManagementService->deletePlugin($plugin);

        $this->pluginService->refreshPlugins($context, new NullIO());

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/install", name="api.action.plugin.install", methods={"POST"})
     */
    public function installPlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/uninstall", name="api.action.plugin.uninstall", methods={"POST"})
     */
    public function uninstallPlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->uninstallPlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/activate", name="api.action.plugin.activate", methods={"POST"})
     */
    public function activatePlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->activatePlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/deactivate", name="api.action.plugin.deactivate", methods={"POST"})
     */
    public function deactivatePlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/update", name="api.action.plugin.update", methods={"POST"})
     */
    public function updatePlugin(QueryDataBag $queryParams, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->updatePlugin($plugin, $context);

        return new JsonResponse($pluginName);
    }
}
