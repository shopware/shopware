<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Returns the cookie-configuration.html.twig template including all cookies returned by the "getCookieGroup"-method
 *
 * Cookies are returned within groups, groups require the "group" attribute
 * A group is structured as described above the "getCookieGroup"-method
 *
 * @RouteScope(scopes={"storefront"})
 */
class CookieController extends StorefrontController
{
    /**
     * @var CookieProviderInterface
     */
    private $cookieProvider;

    public function __construct(CookieProviderInterface $cookieProvider)
    {
        $this->cookieProvider = $cookieProvider;
    }

    /**
     * @Route("/cookie/offcanvas", name="frontend.cookie.offcanvas", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function offcanvas(SalesChannelContext $context): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', ['cookieGroups' => $cookieGroups]);
    }

    /**
     * @Route("/cookie/permission", name="frontend.cookie.permission", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function permission(SalesChannelContext $context): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', ['cookieGroups' => $cookieGroups]);
    }

    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, array $cookieGroups): array
    {
        if ($context->getSalesChannel()->getAnalytics() && $context->getSalesChannel()->getAnalytics()->isActive()) {
            return $cookieGroups;
        }

        $filteredGroups = [];

        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupStatistical') {
                $cookieGroup['entries'] = array_filter($cookieGroup['entries'], function ($item) {
                    return $item['snippet_name'] !== 'cookie.groupStatisticalGoogleAnalytics';
                });
                // Only add statistics cookie group if it has entries
                if (count($cookieGroup['entries']) > 0) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }
            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }
}
