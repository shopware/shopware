<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\AlreadyConnectedGoogleAccountException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\InvalidGoogleAuthorizationCodeException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAccount;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingAuthenticator;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @RouteScope(scopes={"api"})
 */
class GoogleShoppingAccountController extends AbstractController
{
    /**
     * @var DataValidator
     */
    protected $validator;

    /**
     * @var GoogleShoppingAccount
     */
    private $shoppingAccountService;

    /**
     * @var GoogleShoppingAuthenticator
     */
    private $googleShoppingAuthenticator;

    public function __construct(
        DataValidator $validator,
        GoogleShoppingAuthenticator $googleShoppingAuthenticator,
        GoogleShoppingAccount $shoppingAccountService
    ) {
        $this->validator = $validator;
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

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/account/accept-term-of-service", name="api.google-shopping.account.accept-term-of-service", methods={"POST"})
     */
    public function acceptTermOfService(Request $request, GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $this->validateGoogleAccountAcceptance($request);

        $this->shoppingAccountService->acceptTermOfService($id = $shoppingAccount->getId(), $request->request->get('acceptance', false), $googleShoppingRequest);

        return new JsonResponse([
            'data' => $id,
        ]);
    }

    protected function validateGoogleAccountAcceptance(Request $request): void
    {
        $validation = new DataValidationDefinition('google-account-acceptance');
        $validation->add('acceptance', new NotNull(), new Type('bool'));
        $this->validator->validate($request->request->all(), $validation);
    }
}
