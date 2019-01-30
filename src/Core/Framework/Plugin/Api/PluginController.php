<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Api;

use Composer\IO\NullIO;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginInstallerService;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
     * @var PluginInstallerService
     */
    private $pluginInstallerService;

    public function __construct(
        PluginService $pluginService,
        PluginLifecycleService $pluginLifecycleService,
        PluginInstallerService $pluginInstallerService
    ) {
        $this->pluginService = $pluginService;
        $this->pluginLifecycleService = $pluginLifecycleService;
        $this->pluginInstallerService = $pluginInstallerService;
    }

    /**
     * @Route("/api/v{version}/_action/plugin/upload", name="api.action.plugin.upload", methods={"POST"})
     */
    public function uploadPlugin(Request $request, Context $context): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');

        $information = pathinfo($file->getClientOriginalName());

        if ($information['extension'] !== 'zip') {
            unlink($file->getPathname());
            throw new \RuntimeException('extension must be zip');
        }

        $tempFileName = tempnam(sys_get_temp_dir(), $file->getClientOriginalName());
        $tempDirectory = dirname(realpath($tempFileName));

        try {
            $file = $file->move($tempDirectory, $tempFileName);

            $this->pluginInstallerService->extractPluginZip($file->getPathname());
        } catch (\Exception $e) {
            unlink($file->getPathname());
            throw new \RuntimeException($e->getMessage());
        }

        $this->pluginService->refreshPlugins($context, new NullIO());

        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/delete", name="api.action.plugin.delete", methods={"POST"})
     */
    public function deletePlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        if (null !== $plugin->getInstalledAt()) {
            throw new \RuntimeException('can not delete installed plugins');
        }

        $this->pluginInstallerService->deletePlugin($plugin);

        $this->pluginService->refreshPlugins($context, new NullIO());

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/install", name="api.action.plugin.install", methods={"POST"})
     */
    public function installPlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->installPlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/uninstall", name="api.action.plugin.uninstall", methods={"POST"})
     */
    public function uninstallPlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->uninstallPlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/activate", name="api.action.plugin.activate", methods={"POST"})
     */
    public function activatePlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->activatePlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/deactivate", name="api.action.plugin.deactivate", methods={"POST"})
     */
    public function deactivatePlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->deactivatePlugin($plugin, $context);

        return new JsonResponse($plugin);
    }

    /**
     * @Route("/api/v{version}/_action/plugin/{pluginName}/update", name="api.action.plugin.update", methods={"POST"})
     */
    public function updatePlugin(string $pluginName, Request $request, Context $context): Response
    {
        $plugin = $this->pluginService->getPluginByName($pluginName, $context);

        $this->pluginLifecycleService->updatePlugin($plugin, $context);

        return new JsonResponse($plugin);
    }
}
