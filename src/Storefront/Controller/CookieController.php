<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Cookie\SalesChannel\AbstractCookieRoute;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Content\Cookie\Struct\CookieStruct;
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
        try {
            $cookieRouteResponse = $this->cookieRoute->getCookieGroups($request, $salesChannelContext);
            $cookieGroups = $cookieRouteResponse->getCookieGroups();
        } catch (\Throwable $e) {
            $cookieGroups = new CookieGroupCollection();
        }

        return $cookieGroups;
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
        foreach ($cookieGroupCollection as $group) {
            $this->setDefaultValuesForCookieStruct($group);
            foreach ($group->getEntries() as $cookieStruct) {
                $this->setDefaultValuesForCookieStruct($cookieStruct);
            }
        }

        $result = [];
        foreach ($cookieGroupCollection as $group) {
            $result[] = $group->jsonSerialize();
        }

        return $result;
    }

    private function setDefaultValuesForCookieStruct(CookieStruct $cookieStruct): void
    {
        if ($cookieStruct->isSnippetNameUninitializedOrNull()) {
            $cookieStruct->setSnippetName('');
        }
        if ($cookieStruct->isSnippetDescriptionUninitializedOrNull()) {
            $cookieStruct->setSnippetDescription('');
        }
        if ($cookieStruct->isCookieUninitializedOrNull()) {
            $cookieStruct->setCookie('');
        }
        if ($cookieStruct->isValueUninitializedOrNull()) {
            $cookieStruct->setValue('');
        }
        if ($cookieStruct->isExpirationUninitializedOrNull()) {
            $cookieStruct->setExpiration('');
        }
    }
}
