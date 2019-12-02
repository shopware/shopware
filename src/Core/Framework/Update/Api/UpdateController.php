<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopware\Core\Framework\Update\Event\UpdatePreFinishEvent;
use Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopware\Core\Framework\Update\Exception\UpdateFailedException;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\PluginCompatibility;
use Shopware\Core\Framework\Update\Services\RequirementsValidator;
use Shopware\Core\Framework\Update\Steps\DownloadStep;
use Shopware\Core\Framework\Update\Steps\ErrorResult;
use Shopware\Core\Framework\Update\Steps\FinishResult;
use Shopware\Core\Framework\Update\Steps\UnpackStep;
use Shopware\Core\Framework\Update\Steps\ValidResult;
use Shopware\Core\Framework\Update\Struct\Version;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class UpdateController extends AbstractController
{
    public const UPDATE_TOKEN_KEY = 'core.update.token';
    public const UPDATE_PREVIOUS_VERSION_KEY = 'core.update.previousVersion';

    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var string
     */
    private $rootDir;

    /**
     * @var RequirementsValidator
     */
    private $requirementsValidator;

    /**
     * @var PluginCompatibility
     */
    private $pluginCompatibility;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(
        string $rootDir,
        ApiClient $apiClient,
        RequirementsValidator $requirementsValidator,
        PluginCompatibility $pluginCompatibility,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfig,
        string $shopwareVersion
    ) {
        $this->rootDir = $rootDir;
        $this->apiClient = $apiClient;
        $this->requirementsValidator = $requirementsValidator;
        $this->pluginCompatibility = $pluginCompatibility;
        $this->eventDispatcher = $eventDispatcher;
        $this->systemConfig = $systemConfig;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @Route("/api/v{version}/_action/update/check", name="api.custom.updateapi.check", methods={"GET"})
     */
    public function updateApiCheck(): JsonResponse
    {
        try {
            $updates = $this->apiClient->checkForUpdates();

            if (!$updates->isNewer) {
                return new JsonResponse();
            }

            return new JsonResponse($updates);
        } catch (\Throwable $e) {
            return new JsonResponse([
                '__class' => get_class($e),
                '__message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @Route("/api/v{version}/_action/update/check-requirements", name="api.custom.update.check_requirements", methods={"GET"})
     */
    public function checkRequirements(): JsonResponse
    {
        $update = $this->apiClient->checkForUpdates();

        return new JsonResponse($this->requirementsValidator->validate($update));
    }

    /**
     * @Route("/api/v{version}/_action/update/plugin-compatibility", name="api.custom.updateapi.plugin_compatibility", methods={"GET"})
     */
    public function pluginCompatibility(Context $context): JsonResponse
    {
        $update = $this->apiClient->checkForUpdates();

        return new JsonResponse($this->pluginCompatibility->getPluginCompatibilities($update, $context));
    }

    /**
     * @Route("/api/v{version}/_action/update/download-latest-update", name="api.custom.updateapi.download_latest_update", methods={"GET"})
     */
    public function downloadLatestUpdate(Request $request): JsonResponse
    {
        $update = $this->apiClient->checkForUpdates();

        $offset = $request->query->getInt('offset');

        $destination = $this->createDestinationFromVersion($update);

        if ($offset === 0 && file_exists($destination)) {
            unlink($destination);
        }

        $result = (new DownloadStep($update, $destination))->run($offset);

        return $this->toJson($result);
    }

    /**
     * @Route("/api/v{version}/_action/update/unpack", name="api.custom.updateapi.unpack", methods={"GET"})
     */
    public function unpack(Request $request, Context $context): JsonResponse
    {
        $update = $this->apiClient->checkForUpdates();

        $source = $this->createDestinationFromVersion($update);
        $offset = $request->query->getInt('offset');

        $fs = new Filesystem();

        $updateDir = $this->rootDir . '/files/update/';
        $fileDir = $this->rootDir . '/files/update/files';

        $unpackStep = new UnpackStep($source, $fileDir);

        if ($offset === 0) {
            // plugins can subscribe to this events, check compatibility and throw exceptions to prevent the update
            $this->eventDispatcher->dispatch(new UpdatePrePrepareEvent($context, $this->shopwareVersion, $update->version));

            // disable plugins - save active plugins
            $deactivationFilter = $request->query->get('deactivationFilter', PluginCompatibility::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE);
            // TODO: NEXT-5205 - Refactor into DeactivateIncompatiblePluginStep
            $this->pluginCompatibility->deactivateIncompatiblePlugins($update, $context, $deactivationFilter);

            $containerWithoutPlugins = $this->rebootKernelWithoutPlugins();
            $eventDispatcher = $containerWithoutPlugins->get('event_dispatcher');
            // @internal plugins are deactivated
            $eventDispatcher->dispatch(new UpdatePostPrepareEvent($context, $this->shopwareVersion, $update->version));

            $fs->remove($updateDir);
        }

        $result = $unpackStep->run($offset);

        if ($result instanceof FinishResult) {
            $fs->rename($fileDir . '/update-assets/', $updateDir . '/update-assets/');
            $this->replaceRecoveryFiles($fileDir);

            $payload = [
                'clientIp' => $request->getClientIp(),
                'locale' => 'en',
                'version' => $update->version,
            ];

            $updateFilePath = $this->rootDir . '/files/update/update.json';

            if (!file_put_contents($updateFilePath, json_encode($payload))) {
                throw new UpdateFailedException(sprintf('Could not write file %s', $updateFilePath));
            }

            $this->systemConfig->set(self::UPDATE_PREVIOUS_VERSION_KEY, $update->version);

            return new JsonResponse([
                'redirectTo' => $request->getBaseUrl() . '/recovery/update/index.php',
            ]);
        }

        return $this->toJson($result);
    }

    /**
     * @Route("/api/v{version}/_action/update/finish/{token}", defaults={"auth_required"=false}, name="api.custom.updateapi.finish", methods={"GET"})
     */
    public function finish(string $token, Context $context): Response
    {
        if (!$token) {
            return $this->redirectToRoute('administration.index');
        }

        $dbUpdateToken = $this->systemConfig->get(self::UPDATE_TOKEN_KEY);
        if (!$dbUpdateToken || $token !== $dbUpdateToken) {
            return $this->redirectToRoute('administration.index');
        }
        $oldVersion = (string) $this->systemConfig->get(self::UPDATE_PREVIOUS_VERSION_KEY);

        $_unusedPreviousSetting = ignore_user_abort(true);
        $this->eventDispatcher->dispatch(new UpdatePreFinishEvent($context, $oldVersion, $this->shopwareVersion));

        // reboot with plugins
        $container = $this->rebootWithPlugins();
        $container->get('event_dispatcher')->dispatch(new UpdatePostFinishEvent($context, $oldVersion, $this->shopwareVersion));

        return $this->redirectToRoute('administration.index');
    }

    private function rebootKernelWithoutPlugins(): ContainerInterface
    {
        /** @var Kernel $kernel */
        $kernel = $this->container->get('kernel');

        $classLoad = $kernel->getPluginLoader()->getClassLoader();
        $kernel->reboot(null, new StaticKernelPluginLoader($classLoad));

        return $kernel->getContainer();
    }

    private function rebootWithPlugins(): ContainerInterface
    {
        /** @var Kernel $kernel */
        $kernel = $this->container->get('kernel');

        $classLoad = $kernel->getPluginLoader()->getClassLoader();

        $pluginLoader = new DbalKernelPluginLoader($classLoad, null, $this->container->get(Connection::class));

        $kernel->reboot(null, $pluginLoader);

        return $kernel->getContainer();
    }

    private function toJson($result): JsonResponse
    {
        if ($result instanceof ValidResult) {
            return new JsonResponse([
                'valid' => true,
                'offset' => $result->getOffset(),
                'total' => $result->getTotal(),
                'success' => true,
                '_class' => get_class($result),
            ]);
        }

        if ($result instanceof FinishResult) {
            return new JsonResponse([
                'valid' => false,
                'offset' => $result->getOffset(),
                'total' => $result->getTotal(),
                'success' => true,
                '_class' => get_class($result),
            ]);
        }

        if ($result instanceof ErrorResult) {
            return new JsonResponse([
                'valid' => false,
                'success' => false,
                'errorMsg' => $result->getMessage(),
                '_class' => get_class($result),
            ]);
        }

        throw new UpdateFailedException(sprintf('Result type %s can not be mapped.', get_class($result)));
    }

    private function replaceRecoveryFiles(string $fileDir): void
    {
        $recoveryDir = $fileDir . '/recovery';
        if (!is_dir($recoveryDir)) {
            return;
        }

        $iterator = $this->createRecursiveFileIterator($recoveryDir);

        $fs = new Filesystem();

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $sourceFile = $file->getPathname();
            $destinationFile = $this->rootDir . '/' . str_replace($fileDir, '', $file->getPathname());

            $destinationDirectory = dirname($destinationFile);
            $fs->mkdir($destinationDirectory);
            $fs->rename($sourceFile, $destinationFile, true);
        }
    }

    private function createRecursiveFileIterator(string $path): \RecursiveIteratorIterator
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $path,
            \RecursiveDirectoryIterator::SKIP_DOTS
        );

        return new \RecursiveIteratorIterator(
            $directoryIterator,
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
    }

    private function createDestinationFromVersion(Version $version): string
    {
        $filename = 'update_' . $version->sha1 . '.zip';

        return $this->rootDir . '/' . $filename;
    }
}
