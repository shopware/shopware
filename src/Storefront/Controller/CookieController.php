<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the cookie-configuration.html.twig template including all cookies returned by the "getCookieGroup"-method
 *
 * Cookies are returned within groups, groups require the "group" attribute
 * A group is structured as described above the "getCookieGroup"-method
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('framework')]
class CookieController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCookieRoute $cookieRoute,
    ) {
    }

    #[Route(path: '/cookie/offcanvas', name: 'frontend.cookie.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function offcanvas(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $salesChannelContext);
        $response = $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/permission', name: 'frontend.cookie.permission', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function permission(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $salesChannelContext);
        $response = $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', [
            'cookieGroups' => $cookieGroupCollection,
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/consent-offcanvas', name: 'frontend.cookie.consent.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function cookieConsentOffcanvas(Request $request, SalesChannelContext $context): Response
    {
        $featureName = $request->get('featureName', 'wishlist');
        $cookieName = $request->get('cookieName', 'wishlist-enabled');

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-consent-offcanvas.html.twig', [
            'featureName' => $featureName,
            'cookieName' => $cookieName,
        ]);
    }

    private function getCookieGroupsFromCookieRoute(Request $request, SalesChannelContext $salesChannelContext): CookieGroupCollection
    {
        // Create a new request with the translation parameter set to false for Twig templates
        $cookieRequest = $request->duplicate();
        $cookieRequest->query->set('translate', false);

        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($cookieRequest, $salesChannelContext);

        return $cookieRouteResponse->getCookieGroups();
    }
}
