<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleMerchantAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Content\GoogleShopping\Service\GoogleShoppingMerchantAccount;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class EligibilityRequirementController extends AbstractController
{
    /**
     * @var SystemConfigService
     */
    private $systemConfig;

    /**
     * @var GoogleShoppingMerchantAccount
     */
    private $merchantAccountService;

    public function __construct(
        SystemConfigService $systemConfig,
        GoogleShoppingMerchantAccount $merchantAccountService
    ) {
        $this->systemConfig = $systemConfig;
        $this->merchantAccountService = $merchantAccountService;
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/eligibility-requirements", name="api.google-shopping.eligibility.requirements", methods={"GET"})
     */
    public function eligibilityRequirements(GoogleShoppingRequest $googleShoppingRequest)
    {
        if (!$shoppingAccount = $googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        if (!$merchantAccount = $shoppingAccount->getGoogleShoppingMerchantAccount()) {
            throw new ConnectedGoogleMerchantAccountNotFoundException();
        }

        $productExport = $googleShoppingRequest->getSalesChannel()->getProductExports()->first();

        if (!$productExport) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        $storeFrontSalesChannel = $productExport->getStorefrontSalesChannel();

        $configurations = $this->systemConfig->getDomain('core.basicInformation', $storeFrontSalesChannel->getId(), true);

        $siteUrl = $productExport->getSalesChannelDomain()->getUrl();

        return new JsonResponse([
            'data' => [
                'shoppingAdsPolicies' => true,
                'siteIsVerified' => $this->merchantAccountService->isSiteVerified(
                    $siteUrl,
                    $merchantAccount->getMerchantId()
                ),
                'contactPage' => isset($configurations['core.basicInformation.contactPage']),
                'secureCheckoutProcess' => $this->isHttps($siteUrl),
                'revocationPage' => isset($configurations['core.basicInformation.revocationPage']),
                'shippingPaymentInfoPage' => isset($configurations['core.basicInformation.shippingPaymentInfoPage']),
                'completeCheckoutProcess' => !$storeFrontSalesChannel->isMaintenance(),
            ],
        ]);
    }

    private function isHttps($siteUrl): bool
    {
        return substr($siteUrl, 0, 8) === 'https://';
    }
}
