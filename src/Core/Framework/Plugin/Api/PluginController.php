<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Api;

use Composer\IO\NullIO;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\CanNotDeletePluginManagedByComposerException;
use Shopware\Core\Framework\Plugin\Exception\PluginCannotBeDeletedException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Shopware\Core\Framework\Plugin\PluginDefinition;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class PluginController extends AbstractController
{
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

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var PluginDefinition
     */
    private $pluginDefinition;

    public function __construct(
        PluginService $pluginService,
        PluginLifecycleService $pluginLifecycleService,
        PluginManagementService $pluginManagementService,
        ApiVersionConverter $apiVersionConverter,
        PluginDefinition $pluginDefinition
    ) {
        $this->pluginService = $pluginService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->pluginManagementService = $pluginManagementService;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->pluginDefinition = $pluginDefinition;
    }

    /**
     * @Route("/api/v{version}/_action/plugin/upload", name="api.action.plugin.upload", methods={"POST"})
     */
    public function uploadPlugin(Request $request, Context $context): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        if ($file->getMimeType() !== 'application/zip') {
            unlink($file->getPathname());

            throw new PluginNotAZipFileException($file->getMimeType());
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
     * @Route("/api/v{version}/_action/plugin/delete", name="api.action.plugin.delete", methods={"POST"})
     */
    public function deletePlugin(QueryDataBag $queryParams, int $version, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        if ($plugin->getInstalledAt() !== null) {
            throw new PluginCannotBeDeletedException('can not delete installed plugins');
        }

        if ($plugin->getManagedByComposer()) {
            throw new CanNotDeletePluginManagedByComposerException('can not delete plugins managed by composer');
        }

        $this->pluginManagementService->deletePlugin($plugin, $context);

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->pluginDefinition,
            $plugin,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/plugin/refresh", name="api.action.plugin.refresh", methods={"POST"})
     */
    public function refreshPlugin(Request $request, Context $context): Response
    {
        $this->pluginService->refreshPlugins($context, new NullIO());

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/install", name="api.action.plugin.install", methods={"POST"})
     */
    public function installPlugin(QueryDataBag $queryParams, int $version, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->pluginDefinition,
            $plugin,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/plugin/uninstall", name="api.action.plugin.uninstall", methods={"POST"})
     */
    public function uninstallPlugin(QueryDataBag $queryParams, int $version, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $keepUserData = (bool) $queryParams->get('keepUserData', 1);
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->uninstallPlugin($plugin, $context, $keepUserData);

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->pluginDefinition,
            $plugin,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/plugin/activate", name="api.action.plugin.activate", methods={"POST"})
     */
    public function activatePlugin(QueryDataBag $queryParams, int $version, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->activatePlugin($plugin, $context);

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->pluginDefinition,
            $plugin,
            $version
        ));
    }

    /**
     * @Route("/api/v{version}/_action/plugin/deactivate", name="api.action.plugin.deactivate", methods={"POST"})
     */
    public function deactivatePlugin(QueryDataBag $queryParams, int $version, Context $context): JsonResponse
    {
        $pluginName = $queryParams->get('pluginName');
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

        return new JsonResponse($this->apiVersionConverter->convertEntity(
            $this->pluginDefinition,
            $plugin,
            $version
        ));
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
