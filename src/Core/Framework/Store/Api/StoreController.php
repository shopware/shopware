<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Shopware\Core\Framework\Store\Exception\StoreApiException;
use Shopware\Core\Framework\Store\Exception\StoreInvalidCredentialsException;
use Shopware\Core\Framework\Store\Exception\StoreNotAvailableException;
use Shopware\Core\Framework\Store\Exception\StoreTokenMissingException;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\System\User\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package merchant-services
 *
 * @internal
 * @Route(defaults={"_routeScope"={"api"}})
 */
class StoreController extends AbstractController
{
    private StoreClient $storeClient;

    private EntityRepository $pluginRepo;

    private PluginManagementService $pluginManagementService;

    private AbstractExtensionDataProvider $extensionDataProvider;

    private EntityRepository $userRepository;

    public function __construct(
        StoreClient $storeClient,
        EntityRepository $pluginRepo,
        PluginManagementService $pluginManagementService,
        EntityRepository $userRepository,
        AbstractExtensionDataProvider $extensionDataProvider
    ) {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginManagementService = $pluginManagementService;
        $this->userRepository = $userRepository;
        $this->extensionDataProvider = $extensionDataProvider;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed without replacement
     *
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/ping", name="api.custom.store.ping", methods={"GET"})
     */
    public function pingStoreAPI(): Response
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'Route "api.custom.store.ping" is deprecated and will be removed without replacement.'
        );

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
    public function login(Request $request, Context $context): JsonResponse
    {
        $shopwareId = $request->request->get('shopwareId');
        $password = $request->request->get('password');

        if (!\is_string($shopwareId) || !\is_string($password)) {
            throw new StoreInvalidCredentialsException();
        }

        try {
            $this->storeClient->loginWithShopwareId($shopwareId, $password, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse();
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/checklogin", name="api.custom.store.checklogin", methods={"POST"})
     */
    public function checkLogin(Context $context): Response
    {
        try {
            // Throws StoreTokenMissingException if no token is present
            $this->getUserStoreToken($context);

            $userInfo = $this->storeClient->userInfo($context);

            return new JsonResponse([
                'userInfo' => $userInfo,
            ]);
        } catch (StoreTokenMissingException|ClientException $exception) {
            return new JsonResponse([
                'userInfo' => null,
            ]);
        }
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/logout", name="api.custom.store.logout", methods={"POST"})
     */
    public function logout(Context $context): Response
    {
        $context->scope(Context::SYSTEM_SCOPE, function ($context): void {
            $this->userRepository->update([['id' => $context->getSource()->getUserId(), 'storeToken' => null]], $context);
        });

        return new Response();
    }

    /**
     * @deprecated tag:v6.5.0 Unused method will be removed
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/licenses", name="api.custom.store.licenses", methods={"GET"})
     */
    public function getLicenseList(Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        try {
            $licenseList = $this->storeClient->getLicenseList($context);
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
    public function getUpdateList(Context $context): JsonResponse
    {
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);

        try {
            $updatesList = $this->storeClient->getExtensionUpdateList($extensions, $context);
        } catch (ClientException $exception) {
            throw new StoreApiException($exception);
        }

        return new JsonResponse([
            'items' => $updatesList,
            'total' => \count($updatesList),
        ]);
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed, use ExtensionStoreActionsController::downloadExtension() instead
     * @Since("6.0.0.0")
     * @Route("/api/_action/store/download", name="api.custom.store.download", methods={"GET"})
     */
    public function downloadPlugin(QueryDataBag $queryDataBag, Context $context): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'ExtensionStoreActionsController::downloadExtension()')
        );

        $pluginName = (string) $queryDataBag->get('pluginName');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        /** @var PluginEntity|null $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin !== null && $plugin->getManagedByComposer()) {
            throw new CanNotDownloadPluginManagedByComposerException('can not downloads plugins managed by composer from store api');
        }

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $context);
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
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);

        $indexedExtensions = [];

        foreach ($extensions as $extension) {
            $name = $extension->getName();
            $indexedExtensions[$name] = [
                'name' => $name,
                'version' => $extension->getVersion(),
                'active' => $extension->getActive(),
            ];
        }

        try {
            $violations = $this->storeClient->getLicenseViolations($context, $indexedExtensions, $request->getHost());
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
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, false);

        try {
            $this->storeClient->checkForViolations($context, $extensions, $request->getHost());
        } catch (\Exception $e) {
        }

        return new JsonResponse([
            'total' => $extensions->count(),
            'items' => $extensions,
        ]);
    }

    protected function getUserStoreToken(Context $context): string
    {
        $contextSource = $context->getSource();

        if (!$contextSource instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($contextSource));
        }

        $userId = $contextSource->getUserId();
        if ($userId === null) {
            throw new InvalidContextSourceUserException(\get_class($contextSource));
        }

        /** @var UserEntity|null $user */
        $user = $this->userRepository->search(new Criteria([$userId]), $context)->first();

        if ($user === null) {
            throw new StoreTokenMissingException();
        }

        $storeToken = $user->getStoreToken();
        if ($storeToken === null) {
            throw new StoreTokenMissingException();
        }

        return $storeToken;
    }
}
