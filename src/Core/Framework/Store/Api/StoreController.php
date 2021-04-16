<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * @RouteScope(scopes={"api"})
 */
class StoreController extends AbstractStoreController
{
    /**
     * @var StoreClient
     */
    private $storeClient;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var PluginManagementService
     */
    private $pluginManagementService;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var AbstractExtensionDataProvider|null
     */
    private $extensionDataProvider;

    public function __construct(
        StoreClient $storeClient,
        EntityRepositoryInterface $pluginRepo,
        PluginManagementService $pluginManagementService,
        EntityRepositoryInterface $userRepository,
        SystemConfigService $configService,
        ?AbstractExtensionDataProvider $extensionDataProvider
    ) {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginManagementService = $pluginManagementService;
        $this->userRepository = $userRepository;
        $this->configService = $configService;
        parent::__construct($userRepository);
        $this->extensionDataProvider = $extensionDataProvider;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/ping", name="api.custom.store.ping", methods={"GET"})
     */
    public function pingStoreAPI(): Response
    {
        try {
            $this->storeClient->ping();
        } catch (ClientException | ConnectException $exception) {
            throw new StoreNotAvailableException();
        }

        return new Response();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/login", name="api.custom.store.login", methods={"POST"})
     */
    public function login(RequestDataBag $requestDataBag, QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        $shopwareId = $requestDataBag->get('shopwareId');
        $password = $requestDataBag->get('password');
        $language = $queryDataBag->get('language', '');

        if ($shopwareId === null || $password === null) {
            throw new StoreInvalidCredentialsException();
        }

        $this->ensureAdminApiSource($context);

        try {
            $accessTokenStruct = $this->storeClient->loginWithShopwareId($shopwareId, $password, $language, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $this->configService->set('core.store.shopSecret', $accessTokenStruct->getShopSecret());
        $this->configService->set('core.store.shopwareId', $shopwareId);

        $newStoreToken = $accessTokenStruct->getShopUserToken()->getToken();
        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($newStoreToken): void {
            $this->userRepository->update([['id' => $context->getSource()->getUserId(), 'storeToken' => $newStoreToken]], $context);
        });

        return new JsonResponse();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/checklogin", name="api.custom.store.checklogin", methods={"POST"})
     */
    public function checkLogin(Context $context): Response
    {
        $tokenExists = false;

        try {
            $storeToken = $this->getUserStoreToken($context);
            if ($storeToken !== '') {
                $tokenExists = true;
            }
        } catch (StoreTokenMissingException $storeTokenMissingException) {
        }

        return new JsonResponse(['storeTokenExists' => $tokenExists]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/logout", name="api.custom.store.logout", methods={"POST"})
     */
    public function logout(Context $context): Response
    {
        $this->ensureAdminApiSource($context);

        $context->scope(Context::SYSTEM_SCOPE, function ($context): void {
            $this->userRepository->update([['id' => $context->getSource()->getUserId(), 'storeToken' => null]], $context);
        });

        return new Response();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/licenses", name="api.custom.store.licenses", methods={"GET"})
     */
    public function getLicenseList(QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        $storeToken = $this->getUserStoreToken($context);
        $language = $queryDataBag->get('language', '');

        try {
            $licenseList = $this->storeClient->getLicenseList($storeToken, $language, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $licenseList,
            'total' => \count($licenseList),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/updates", name="api.custom.store.updates", methods={"GET"})
     */
    public function getUpdateList(Request $request, Context $context): JsonResponse
    {
        $language = $request->query->get('language', '');

        if ($this->extensionDataProvider) {
            $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);

            try {
                $updatesList = $this->storeClient->getExtensionUpdateList($extensions, $context);
            } catch (ClientException $exception) {
                throw new StoreApiException($exception);
            }
        } else {
            /** @var PluginCollection $plugins */
            $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

            try {
                $storeToken = $this->getUserStoreToken($context);
            } catch (StoreTokenMissingException $e) {
                $storeToken = null;
            }

            try {
                $updatesList = $this->storeClient->getUpdatesList($storeToken, $plugins, $language, $request->getHost(), $context);
            } catch (ClientException $exception) {
                throw new StoreApiException($exception);
            }
        }

        return new JsonResponse([
            'items' => $updatesList,
            'total' => \count($updatesList),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/download", name="api.custom.store.download", methods={"GET"})
     */
    public function downloadPlugin(QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        $pluginName = $queryDataBag->get('pluginName');
        $language = $queryDataBag->get('language', '');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->getManagedByComposer()) {
            throw new CanNotDownloadPluginManagedByComposerException('can not downloads plugins managed by composer from store api');
        }

        $unauthenticated = $queryDataBag->has('unauthenticated');
        if ($unauthenticated) {
            $storeToken = '';
        } else {
            $storeToken = $this->getUserStoreToken($context);
        }

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $storeToken, $language, !$unauthenticated);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        return new JsonResponse(null, Response::HTTP_OK);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/license-violations", name="api.custom.store.license-violations", methods={"POST"})
     */
    public function getLicenseViolations(Request $request, Context $context): JsonResponse
    {
        $language = $request->query->get('language', '');

        if ($this->extensionDataProvider) {
            $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);
        } else {
            /** @var PluginCollection $extensions */
            $extensions = $this->pluginRepo->search(new Criteria(), $context)->getEntities();
        }

        $indexedExtensions = [];

        foreach ($extensions as $extension) {
            $indexedExtensions[$extension->getName()] = $extension->getVersion();
        }

        try {
            $storeToken = $this->getUserStoreToken($context);
        } catch (StoreTokenMissingException $e) {
            $storeToken = null;
        }

        try {
            $violations = $this->storeClient->getLicenseViolations($storeToken, $indexedExtensions, $language, $request->getHost());
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $violations,
            'total' => \count($violations),
        ]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/plugin/search", name="api.action.store.plugin.search", methods={"POST"})
     */
    public function searchPlugins(Request $request, Context $context): Response
    {
        if ($this->extensionDataProvider) {
            $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);
        } else {
            /** @var PluginCollection $extensions */
            $extensions = $this->pluginRepo->search(new Criteria(), $context)->getEntities();
        }

        try {
            $language = $request->query->get('language', 'en-GB');

            try {
                $storeToken = $this->getUserStoreToken($context);
            } catch (StoreTokenMissingException $e) {
                $storeToken = null;
            }

            $this->storeClient->checkForViolations($storeToken, $extensions, $language, $request->getHost());
        } catch (\Exception $e) {
            // extension list should always work
        }

        return new JsonResponse([
            'total' => $extensions->count(),
            'items' => $extensions,
        ]);
    }
}
