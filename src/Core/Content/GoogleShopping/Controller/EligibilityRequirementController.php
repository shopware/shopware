<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Controller;

use Shopware\Core\Content\GoogleShopping\Exception\ConnectedGoogleAccountNotFoundException;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
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

    public function __construct(
        SystemConfigService $systemConfig
    ) {
        $this->systemConfig = $systemConfig;
    }

    /**
     * @Route("/api/v{version}/_action/sales-channel/{salesChannelId}/google-shopping/eligibility-requirements", name="api.google-shopping.eligibility.requirements", methods={"GET"})
     */
    public function eligibilityRequirements(GoogleShoppingRequest $googleShoppingRequest): JsonResponse
    {
        if (!$googleShoppingRequest->getGoogleShoppingAccount()) {
            throw new ConnectedGoogleAccountNotFoundException();
        }

        $productExport = $googleShoppingRequest->getSalesChannel()->getProductExports()->first();
        if (!$productExport) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        $storefrontSalesChannel = $productExport->getStorefrontSalesChannel();

        $configurations = $this->systemConfig->getDomain('core.basicInformation', $storefrontSalesChannel->getId(), true);

        return new JsonResponse([
            'data' => [
                'shoppingAdsPolicies' => true,
                'contactPage' => isset($configurations['core.basicInformation.contactPage']),
                'secureCheckoutProcess' => $this->isHttps($productExport->getSalesChannelDomain()->getUrl()),
                'revocationPage' => isset($configurations['core.basicInformation.revocationPage']),
                'shippingPaymentInfoPage' => isset($configurations['core.basicInformation.shippingPaymentInfoPage']),
                'completeCheckoutProcess' => !$storefrontSalesChannel->isMaintenance(),
            ],
        ]);
    }

    private function isHttps($siteUrl): bool
    {
        return substr($siteUrl, 0, 8) === 'https://';
    }
}
