<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount\GoogleShoppingMerchantAccountEntity;
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
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/info", name="api.google-shopping.merchant.info", methods={"GET"})
     */
    public function getInfo(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateMerchantAccountConnected($googleShoppingRequest);

        return new JsonResponse([
            'data' => $this->merchantAccountService->getInfo($merchantAccount->getMerchantId()),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/status", name="api.google-shopping.merchant.status", methods={"GET"})
     */
    public function getStatus(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateMerchantAccountConnected($googleShoppingRequest);

        return new JsonResponse([
            'data' => $this->merchantAccountService->getStatus($merchantAccount->getMerchantId()),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/list", name="api.google-shopping.merchant.list", methods={"GET"})
     */
    public function list(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $this->validateGoogleAccountConnected($googleShoppingRequest);

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

        $shoppingAccount = $this->validateGoogleAccountConnected($googleShoppingRequest);

        if ($shoppingAccount->getGoogleShoppingMerchantAccount()) {
            throw new AlreadyConnectedGoogleMerchantAccountException();
        }

        $salesChannel = $googleShoppingRequest->getSalesChannel();

        $siteUrl = $this->merchantAccountService->getSalesChannelDomain($salesChannel->getId(), $googleShoppingRequest->getContext())->getUrl();

        $this->merchantAccountService->updateWebsiteUrl($googleMerchantId, $siteUrl);

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
        $merchantAccount = $this->validateMerchantAccountConnected($googleShoppingRequest);

        $merchantAccountId = $merchantAccount->getId();

        $this->merchantAccountService->delete($merchantAccountId, $googleShoppingRequest->getContext());

        return new JsonResponse([
            'data' => $merchantAccountId,
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/claim-url", name="api.google-shopping.merchant.claim-url", methods={"POST"})
     */
    public function claimWebsiteUrl(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        $merchantAccount = $this->validateMerchantAccountConnected($googleShoppingRequest);

        return new JsonResponse([
            'data' => $this->merchantAccountService->claimWebsiteUrl($merchantAccount->getMerchantId(), true),
        ]);
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/merchant/update", name="api.google-shopping.merchant.update", methods={"POST"})
     */
    public function update(GoogleShoppingRequest $googleShoppingRequest, Request $request): JsonResponse
    {
        $merchantAccountDb = $this->validateMerchantAccountConnected($googleShoppingRequest);

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
        $merchantAccountDb = $this->validateMerchantAccountConnected($googleShoppingRequest);

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

    private function validateMerchantAccountConnected(GoogleShoppingRequest $googleShoppingRequest): GoogleShoppingMerchantAccountEntity
    {
        $shoppingAccount = $this->validateGoogleAccountConnected($googleShoppingRequest);

        if (!$merchantAccount = $shoppingAccount->getGoogleShoppingMerchantAccount()) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        return $merchantAccount;
    }

    private function validateGoogleAccountConnected(GoogleShoppingRequest $googleShoppingRequest)
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        return $shoppingAccount;
    }
}
