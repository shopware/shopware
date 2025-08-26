<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for cache-compatible affiliate tracking functionality.
 * Provides AJAX endpoints to store affiliate tracking parameters in session
 * when pages are served from full-page caches like Varnish.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AffiliateTrackingController extends StorefrontController
{
    #[Route(
        path: '/affiliate-tracking',
        name: 'frontend.affiliate.tracking',
        methods: ['POST']
    )]
    public function storeAffiliateInformation(Request $request): Response
    {
        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        // Only process storefront routes
        if (!\in_array(StorefrontRouteScope::ID, $scopes, true)) {
            return new Response();
        }

        if (!$request->hasSession()) {
            return new Response();
        }

        $session = $request->getSession();
        $payload = $request->getPayload();

        $affiliateCode = $payload->get(OrderService::AFFILIATE_CODE_KEY);
        if ($affiliateCode) {
            $session->set(OrderService::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        $campaignCode = $payload->get(OrderService::CAMPAIGN_CODE_KEY);
        if ($campaignCode) {
            $session->set(OrderService::CAMPAIGN_CODE_KEY, $campaignCode);
        }

        return new Response();
    }
}
