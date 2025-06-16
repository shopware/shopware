<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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
#[Route(defaults: ['_routeScope' => ['storefront']])]
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
            'cookieGroups' => $this->transformCookieGroupForTwig($cookieGroupCollection),
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/permission', name: 'frontend.cookie.permission', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function permission(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $cookieGroupCollection = $this->getCookieGroupsFromCookieRoute($request, $salesChannelContext);
        $response = $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', [
            'cookieGroups' => $this->transformCookieGroupForTwig($cookieGroupCollection),
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    private function getCookieGroupsFromCookieRoute(Request $request, SalesChannelContext $salesChannelContext): CookieGroupCollection
    {
        // Create a new request with the translation parameter set to false for Twig templates
        $cookieRequest = $request->duplicate();
        $cookieRequest->query->set('translate', false);

        $cookieRouteResponse = $this->cookieRoute->getCookieGroups($cookieRequest, $salesChannelContext);

        return $cookieRouteResponse->getCookieGroups();
    }

    /**
     * Transforms the cookie group collection to a format suitable for Twig.
     *
     * Ensures that all snippet names and descriptions are initialized or set to empty strings.
     *
     * @return array<string|int, mixed>
     */
    private function transformCookieGroupForTwig(CookieGroupCollection $cookieGroupCollection): array
    {
        $result = [];
        foreach ($cookieGroupCollection as $group) {
            $this->setDefaultValuesForCookieStruct($group);
            $result[] = $group->jsonSerialize();
            foreach ($group->entries as $pos => $cookieStruct) {
                $this->setDefaultValuesForCookieStruct($cookieStruct);
                $result[\count($result) - 1]['entries'][$pos] = $cookieStruct->jsonSerialize();
            }
        }

        return $result;
    }

    private function setDefaultValuesForCookieStruct(CookieEntry|CookieGroup $cookieStruct): void
    {
        if (!isset($cookieStruct->snippet_name)) {
            $cookieStruct->snippet_name = '';
        }
        if (!isset($cookieStruct->snippet_description)) {
            $cookieStruct->snippet_description = '';
        }
        if (!isset($cookieStruct->cookie)) {
            $cookieStruct->cookie = '';
        }
        if (!isset($cookieStruct->value)) {
            $cookieStruct->value = '';
        }
        if (!isset($cookieStruct->expiration)) {
            $cookieStruct->expiration = '';
        }
    }
}
