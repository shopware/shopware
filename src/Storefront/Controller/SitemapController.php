<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class SitemapController extends StorefrontController
{
    /**
     * @var SitemapPageLoader
     */
    private $sitemapPageLoader;

    public function __construct(SitemapPageLoader $sitemapPageLoader)
    {
        $this->sitemapPageLoader = $sitemapPageLoader;
    }

    /**
     * @Route("/sitemap.xml", name="frontend.sitemap.xml", methods={"GET"}, defaults={"_format"="xml"})
     */
    public function sitemapXml(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->sitemapPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/sitemap/sitemap.xml.twig', ['page' => $page]);
    }
}
