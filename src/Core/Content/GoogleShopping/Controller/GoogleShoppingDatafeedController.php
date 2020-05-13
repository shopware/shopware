<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleMerchantAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\DatafeedNotFoundException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingDatafeed;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class GoogleShoppingDatafeedController extends AbstractController
{
    /**
     * @var GoogleShoppingDatafeed
     */
    private $googleShoppingDatafeed;

    public function __construct(
        GoogleShoppingDatafeed $googleShoppingDatafeed
    ) {
        $this->googleShoppingDatafeed = $googleShoppingDatafeed;
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/datafeed/sync", name="api.google-shopping.datafeed.sync", methods={"POST"})
     */
    public function syncProduct(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateGoogleShoppingConnected($googleShoppingRequest);

        $datafeed = $this->googleShoppingDatafeed->write($merchantAccount, $googleShoppingRequest->getSalesChannel(), $googleShoppingRequest->getContext());

        $this->googleShoppingDatafeed->syncProduct($merchantAccount->getMerchantId(), $datafeed['id']);

        return new JsonResponse([
            'data' => $datafeed,
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/datafeed", name="api.google-shopping.datafeed", methods={"GET"})
     */
    public function getDatafeed(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateGoogleShoppingConnected($googleShoppingRequest);

        if (!$merchantAccount->getDatafeedId()) {
            throw new DatafeedNotFoundException();
        }

        return new JsonResponse([
            'data' => $this->googleShoppingDatafeed->get($merchantAccount),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/datafeed/status", name="api.google-shopping.datafeed.status", methods={"GET"})
     */
    public function getDatafeedStatus(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateGoogleShoppingConnected($googleShoppingRequest);

        if (!$merchantAccount->getDatafeedId()) {
            throw new DatafeedNotFoundException();
        }

        return new JsonResponse([
            'data' => $this->googleShoppingDatafeed->getStatus($merchantAccount),
        ]);
    }

    private function validateGoogleShoppingConnected(GoogleShoppingRequest $googleShoppingRequest)
    {
        if (!$googleShopping = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        if (!$merchantAccount = $googleShopping->getGoogleShoppingMerchantAccount()) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        return $merchantAccount;
    }
}
