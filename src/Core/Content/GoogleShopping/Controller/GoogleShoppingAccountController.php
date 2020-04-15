<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\AlreadyConnectedGoogleAccountException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleAuthorizationCodeException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAuthenticator;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class GoogleShoppingAccountController extends AbstractController
{
    /**
     * @var GoogleShoppingAccount
     */
    private $shoppingAccountService;

    /**
     * @var GoogleShoppingAuthenticator
     */
    private $googleShoppingAuthenticator;

    public function __construct(
        GoogleShoppingAuthenticator $googleShoppingAuthenticator,
        GoogleShoppingAccount $shoppingAccountService
    ) {
        $this->shoppingAccountService = $shoppingAccountService;
        $this->googleShoppingAuthenticator = $googleShoppingAuthenticator;
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/account/connect", name="api.google-shopping.auth.connect", methods={"POST"})
     */
    public function connect(string $salesChannelId, Request $request, GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $code = $request->request->get('code');

        if (empty($code)) {
            throw new InvalidGoogleAuthorizationCodeException();
        }

        if ($googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new AlreadyConnectedGoogleAccountException();
        }

        $credential = $this->googleShoppingAuthenticator->authorize($code);

        $this->shoppingAccountService->create($credential, $salesChannelId, $googleShoppingRequest);

        return new JsonResponse([
            'data' => $credential->getIdTokenParts(),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/account/disconnect", name="api.google-shopping.auth.disconnect", methods={"POST"})
     */
    public function disconnect(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $this->shoppingAccountService->delete($id = $shoppingAccount->getId(), $shoppingAccount->getCredential(), $googleShoppingRequest);

        return new JsonResponse(['id' => $id]);
    }
}
