<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
    public function offcanvas(): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', ['cookieGroups' => $cookieGroups]);
    }

    /**
     * @Route("/cookie/permission", name="frontend.cookie.permission", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function permission(): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', ['cookieGroups' => $cookieGroups]);
    }
}
