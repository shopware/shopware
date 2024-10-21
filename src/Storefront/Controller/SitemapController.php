<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoadedHook;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('services-settings')]
class SitemapController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SitemapPageLoader $sitemapPageLoader,
        private readonly SitemapFileRoute $sitemapFileRoute
    ) {
    }

    #[Route(path: '/sitemap.xml', name: 'frontend.sitemap.xml', defaults: ['_format' => 'xml'], methods: ['GET'])]
    public function sitemapXml(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->sitemapPageLoader->load($request, $context);

        $this->hook(new SitemapPageLoadedHook($page, $context));

        $response = $this->renderStorefront('@Storefront/storefront/page/sitemap/sitemap.xml.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/xml; charset=utf-8');

        return $response;
    }

    #[Route(path: '/sitemap/{filePath}', name: 'frontend.sitemap.proxy', requirements: ['filePath' => '.+\.xml\.gz'], methods: ['GET'])]
    public function sitemapProxy(SalesChannelContext $context, Request $request, string $filePath): Response
    {
        return $this->sitemapFileRoute->getSitemapFile($request, $context, $filePath);
    }
}
