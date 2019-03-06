<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Api;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginInstallerService;
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
     * @var PluginInstallerService
     */
    private $pluginInstallerService;

    public function __construct(StoreClient $storeClient, EntityRepositoryInterface $pluginRepo, PluginInstallerService $pluginInstallerService)
    {
        $this->storeClient = $storeClient;
        $this->pluginRepo = $pluginRepo;
        $this->pluginInstallerService = $pluginInstallerService;
    }

    /**
     * @Route("/api/v{version}/_custom/store/login", name="api.custom.store.login", methods={"POST"})
     */
    public function login(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        $accessTokenStruct = $this->storeClient->loginWithShopwareId($data['shopwareId'], $data['password']);

        $response = new JsonResponse($accessTokenStruct->toArray());
        $response->headers->setCookie(new Cookie('store_token', $accessTokenStruct->getToken()));

        return $response;
    }

    /**
     * @Route("/api/v{version}/_custom/store/checklogin", name="api.custom.store.checklogin", methods={"GET"})
     */
    public function checkLogin(Request $request): Response
    {
        $token = $request->cookies->get('store_token');

        $isLoggedIn = $this->storeClient->checkLogin($token);

        $response = new JsonResponse([
            'success' => $isLoggedIn,
        ]);

        return $response;
    }

    /**
     * @Route("/api/v{version}/_custom/store/licenses", name="api.custom.store.licenses", methods={"GET"})
     */
    public function getLicenseList(Request $request, Context $context): Response
    {
        $token = $request->cookies->get('store_token', '');

        $licenseList = $this->storeClient->getLicenseList($token, $context);

        return new JsonResponse([
            'items' => $licenseList,
            'total' => count($licenseList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_custom/store/updates", name="api.custom.store.updates", methods={"GET"})
     */
    public function getUpdateList(Request $request): Response
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        /** @var PluginCollection $plugins */
        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

        $updatesList = $this->storeClient->getUpdatesList($plugins);

        return new JsonResponse([
            'items' => $updatesList,
            'total' => count($updatesList),
        ]);
    }

    /**
     * @Route("/api/v{version}/_custom/store/download", name="api.custom.store.download", methods={"GET"})
     */
    public function downloadPlugin(Request $request, Context $context): Response
    {
        $token = $request->cookies->get('store_token', '');

        $data = $this->storeClient->getDownloadDataForPlugin($request->query->get('pluginName'), $token);

        $success = $this->pluginInstallerService->downloadStorePlugin($data, $context);

        return new JsonResponse(['success' => $success]);
    }
}
