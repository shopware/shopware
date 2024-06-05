<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Api;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('checkout')]
class InAppPurchasesController extends AbstractController
{
    #[Route(path: '/api/store/active-in-app-purchases', name: 'api.store.active-in-app-purchases', methods: ['GET'])]
    public function activeInAppPurchases(Context $context): JsonResponse
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw StoreException::invalidContextSource(AdminApiSource::class, $source::class);
        }

        if ($source->getIntegrationId() === null) {
            throw StoreException::missingIntegrationInContextSource($source::class);
        }

        $appId = (string) $source->getIntegrationId();

        return new JsonResponse(
            ['inAppPurchases' => InAppPurchase::getByExtension($appId)]
        );
    }

    #[Route(path: '/api/store/check-in-app-purchase-active', name: 'api.store.check-in-app-purchase-active', methods: ['POST'])]
    public function checkInAppPurchaseActive(Request $request): JsonResponse
    {
        $identifier = \trim($request->request->getString('identifier'));
        if (!$identifier) {
            throw StoreException::missingRequestParameter('identifier');
        }

        return new JsonResponse(
            ['isActive' => InAppPurchase::isActive($identifier)]
        );
    }
}
