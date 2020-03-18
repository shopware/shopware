<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\AlreadyConnectedGoogleMerchantAccountException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleMerchantAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantAccount;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class GoogleShoppingMerchantController extends AbstractController
{
    /**
     * @var GoogleShoppingMerchantAccount
     */
    private $merchantAccountService;

    public function __construct(
        GoogleShoppingMerchantAccount $merchantAccountService
    ) {
        $this->merchantAccountService = $merchantAccountService;
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/info", name="api.google-shopping.merchant.get", methods={"GET"})
     */
    public function getInfo(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$googleShopping = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        if (!$merchantAccount = $googleShopping->getGoogleShoppingMerchantAccount()) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        return new JsonResponse([
            'data' => $this->merchantAccountService->getInfo($merchantAccount->getMerchantId()),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/list", name="api.google-shopping.merchant.list", methods={"GET"})
     */
    public function list(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        return new JsonResponse([
            'data' => $this->merchantAccountService->list(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/assign", name="api.google-shopping.merchant.assign", methods={"POST"})
     */
    public function assign(Request $request, GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $googleMerchantId = $request->request->get('merchantId');

        if (!$googleMerchantId) {
            throw new MissingRequestParameterException('merchantId');
        }

        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        if ($shoppingAccount->getGoogleShoppingMerchantAccount()) {
            throw new AlreadyConnectedGoogleMerchantAccountException();
        }

        $merchantAccountId = $this->merchantAccountService->create(
            $googleMerchantId,
            $shoppingAccount->getId(),
            $googleShoppingRequest->getContext()
        );

        return new JsonResponse([
            'data' => $merchantAccountId,
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/unassign", name="api.google-shopping.merchant.unassign", methods={"POST"})
     */
    public function unassign(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $merchantAccountDb = $shoppingAccount->getGoogleShoppingMerchantAccount();

        if (!$merchantAccountDb) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        $merchantAccountId = $merchantAccountDb->getId();

        $this->merchantAccountService->delete($merchantAccountId, $googleShoppingRequest->getContext());

        return new JsonResponse([
            'data' => $merchantAccountId,
        ]);
    }
}
