<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\AlreadyConnectedGoogleMerchantAccountException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleMerchantAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantAccount;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingShippingSetting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Url;

/**
 * @RouteScope(scopes={"api"})
 */
class GoogleShoppingMerchantController extends AbstractController
{
    /**
     * @var DataValidator
     */
    protected $validator;

    /**
     * @var GoogleShoppingMerchantAccount
     */
    private $merchantAccountService;

    /**
     * @var GoogleShoppingShippingSetting
     */
    private $shippingSettingService;

    public function __construct(
        DataValidator $validator,
        GoogleShoppingMerchantAccount $merchantAccountService,
        GoogleShoppingShippingSetting $shippingSettingService
    ) {
        $this->validator = $validator;
        $this->merchantAccountService = $merchantAccountService;
        $this->shippingSettingService = $shippingSettingService;
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

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/update", name="api.google-shopping.merchant.update", methods={"POST"})
     */
    public function update(GoogleShoppingRequest $googleShoppingRequest, Request $request): JsonResponse
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $merchantAccountDb = $shoppingAccount->getGoogleShoppingMerchantAccount();

        if (!$merchantAccountDb) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        $this->validateUpdateAccountParameters($request);

        return new JsonResponse([
            'data' => $this->merchantAccountService->update($request, $merchantAccountDb->getMerchantId()),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/setup-shipping", name="api.google-shopping.merchant.setup.shipping", methods={"POST"})
     */
    public function setupShipping(GoogleShoppingRequest $googleShoppingRequest, Request $request): JsonResponse
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $merchantAccountDb = $shoppingAccount->getGoogleShoppingMerchantAccount();

        if (!$merchantAccountDb) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        $this->validateShippingSettingParameters($request);

        return new JsonResponse([
            'data' => $this->shippingSettingService->update($googleShoppingRequest, $merchantAccountDb->getMerchantId(), (float) $request->get('flatRate')),
        ]);
    }

    protected function validateShippingSettingParameters(Request $request): void
    {
        $validation = new DataValidationDefinition('google-shipping-setting');
        $validation->add('flatRate', new NotBlank(), new Type('numeric'));
        $this->validator->validate($request->request->all(), $validation);
    }

    private function validateUpdateAccountParameters(Request $request): void
    {
        $validation = new DataValidationDefinition('google-account-setting');
        $validation->add('websiteUrl', new NotBlank(), new Url());
        $validation->add('name', new NotBlank(), new Type('string'));
        $validation->add('country', new NotBlank(), new Type('string'));
        $validation->add('adultContent', new Type('boolean'));
        $this->validator->validate($request->request->all(), $validation);
    }
}
