<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Store\Services\AbstractExtensionLifecycle;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Event\UpdatePostPrepareEvent;
use Shopware\Core\Framework\Update\Event\UpdatePrePrepareEvent;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Steps\DeactivateExtensionsStep;
use Shopware\Core\Framework\Update\UpdateException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], PlatformRequest::ATTRIBUTE_OPENAPI => false])]
class UpdateController extends AbstractController
{
    public const UPDATE_PREVIOUS_VERSION_KEY = 'core.update.previousVersion';

    /**
     * @internal
     */
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly StoreClient $storeClient,
        private readonly ExtensionCompatibility $extensionCompatibility,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfig,
        private readonly AbstractExtensionLifecycle $extensionLifecycleService,
        private readonly string $shopwareVersion,
        private readonly bool $shopwareUpdateEnabled = true,
        private readonly bool $updateModuleHidden = false,
        private readonly bool $clusterSetup = false,
    ) {
    }

    #[Route(
        path: '/api/_action/update/check',
        name: 'api.custom.updateapi.check',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:core:update']],
        methods: [Request::METHOD_GET]
    )]
    public function updateApiCheck(): JsonResponse
    {
        $this->ensureUpdateModuleVisible();

        $updates = $this->apiClient->checkForUpdates();

        if (version_compare($this->shopwareVersion, $updates->version, '>=')) {
            return new JsonResponse();
        }

        return new JsonResponse([
            ...$updates->jsonSerialize(),
            'autoUpdateEnabled' => $this->shopwareUpdateEnabled,
            'clusterSetup' => $this->clusterSetup,
        ]);
    }

    #[Route(
        path: '/api/_action/update/check-requirements',
        name: 'api.custom.update.check_requirements',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:core:update']],
        methods: [Request::METHOD_GET]
    )]
    public function checkLicense(): JsonResponse
    {
        $this->ensureUpdateModuleVisible();

        $licenseHost = $this->systemConfig->getString('core.store.licenseHost');

        return new JsonResponse([
            'isValid' => $licenseHost === '' || $this->storeClient->isShopUpgradeable(),
        ]);
    }

    #[Route(
        '/api/_action/update/extension-compatibility',
        name: 'api.custom.updateapi.extension_compatibility',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:core:update', 'system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function extensionCompatibility(Context $context): JsonResponse
    {
        $this->ensureUpdateModuleVisible();

        $update = $this->apiClient->checkForUpdates();

        return new JsonResponse($this->extensionCompatibility->getExtensionCompatibilities($update, $context));
    }

    #[Route(
        path: '/api/_action/update/download-recovery',
        name: 'api.custom.updateapi.download-recovery',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:core:update', 'system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function downloadLatestRecovery(): Response
    {
        $this->ensureAutoUpdateEnabled();

        if ($this->clusterSetup) {
            throw UpdateException::clusterSetupNotSupported();
        }

        $this->apiClient->downloadRecoveryTool();

        return new NoContentResponse();
    }

    #[Route(
        path: '/api/_action/update/deactivate-plugins',
        name: 'api.custom.updateapi.deactivate-plugins',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:core:update', 'system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function deactivateExtensions(Request $request, Context $context): JsonResponse
    {
        $this->ensureAutoUpdateEnabled();

        $update = $this->apiClient->checkForUpdates();

        $offset = $request->query->getInt('offset');

        if ($offset === 0) {
            // plugins can subscribe to these events, check compatibility and throw exceptions to prevent the update
            $this->eventDispatcher->dispatch(
                new UpdatePrePrepareEvent($context, $this->shopwareVersion, $update->version)
            );
        }

        // disable plugins - save active plugins
        $deactivationFilter = (string) $request->query->get(
            'deactivationFilter',
            ExtensionCompatibility::PLUGIN_DEACTIVATION_FILTER_NOT_COMPATIBLE
        );

        $deactivateExtensionsStep = new DeactivateExtensionsStep(
            $update,
            $deactivationFilter,
            $this->extensionCompatibility,
            $this->extensionLifecycleService,
            $this->systemConfig,
            $context
        );

        $result = $deactivateExtensionsStep->run($offset);

        if ($result->getOffset() === $result->getTotal()) {
            $containerWithoutPlugins = $this->rebootKernelWithoutPlugins();

            // @internal plugins are deactivated
            $containerWithoutPlugins->get('event_dispatcher')->dispatch(
                new UpdatePostPrepareEvent($context, $this->shopwareVersion, $update->version)
            );
        }

        return new JsonResponse([
            'offset' => $result->getOffset(),
            'total' => $result->getTotal(),
        ]);
    }

    private function ensureUpdateModuleVisible(): void
    {
        if ($this->updateModuleHidden) {
            throw UpdateException::updateModuleHidden();
        }
    }

    private function ensureAutoUpdateEnabled(): void
    {
        $this->ensureUpdateModuleVisible();

        if (!$this->shopwareUpdateEnabled) {
            throw UpdateException::autoUpdateDisabled();
        }
    }

    private function rebootKernelWithoutPlugins(): ContainerInterface
    {
        $kernel = $this->container->get('kernel');

        $classLoad = $kernel->getPluginLoader()->getClassLoader();
        $kernel->reboot(null, new StaticKernelPluginLoader($classLoad));

        return $kernel->getContainer();
    }
}
