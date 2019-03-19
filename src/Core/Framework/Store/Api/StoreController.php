<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StoreController extends AbstractController
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
     * @var PluginLifecycleService
     */
    private $pluginLifecycleService;

    public function __construct(
        StoreClient $storeClient,
        EntityRepositoryInterface $pluginRepo,
        PluginManagementService $pluginManagementService,
        PluginLifecycleService $pluginLifecycleService
    ) {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginManagementService = $pluginManagementService;
        $this->pluginLifecycleService = $pluginLifecycleService;
    }

    /**
     * @Route("/api/v{version}/_action/store/login", name="api.custom.store.login", methods={"POST"})
     */
    public function login(Request $request): JsonResponse
    {
        $shopwareId = $request->request->get('shopwareId');
        $password = $request->request->get('password');

        $accessTokenStruct = $this->storeClient->loginWithShopwareId($shopwareId, $password);

        $response = new JsonResponse($accessTokenStruct->toArray());
        $response->headers->setCookie(new Cookie('store_token', $accessTokenStruct->getToken()));

        return $response;
    }

    /**
     * @Route("/api/v{version}/_action/store/checklogin", name="api.custom.store.checklogin", methods={"GET"})
     */
    public function checkLogin(Request $request): JsonResponse
    {
        $token = $request->cookies->get('store_token');
        if ($token === null) {
            return new JsonResponse([], Response::HTTP_UNAUTHORIZED);
        }
        $this->storeClient->checkLogin($token);

        return new JsonResponse();
    }

    /**
     * @Route("/api/v{version}/_action/store/licenses", name="api.custom.store.licenses", methods={"GET"})
     */
    public function getLicenseList(Request $request, Context $context): JsonResponse
    {
        $token = $request->cookies->get('store_token', '');

        $licenseList = $this->storeClient->getLicenseList($token, $context);

        return new JsonResponse([
            'items' => $licenseList,
            'total' => count($licenseList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/updates", name="api.custom.store.updates", methods={"GET"})
     */
    public function getUpdateList(Context $context): JsonResponse
    {
        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        $updatesList = $this->storeClient->getUpdatesList($plugins);

        return new JsonResponse([
            'items' => $updatesList,
            'total' => count($updatesList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/store/download", name="api.custom.store.download", methods={"GET"})
     */
    public function downloadPlugin(Request $request, Context $context): JsonResponse
    {
        $token = $request->cookies->get('store_token', '');

        $data = $this->storeClient->getDownloadDataForPlugin($request->query->get('pluginName'), $token);

        $statusCode = $this->pluginManagementService->downloadStorePlugin($data->getLocation(), $context);
        if ($statusCode !== Response::HTTP_OK) {
            return new JsonResponse([], $statusCode);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $request->query->get('pluginName')));

        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepo->search($criteria, $context)->first();

        if ($plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
        }

        return new JsonResponse();
    }
}
