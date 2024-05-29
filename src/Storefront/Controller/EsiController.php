<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class EsiController extends StorefrontController
{
    public function __construct(
        private readonly HeaderPageletLoaderInterface $headerLoader,
        private readonly FooterPageletLoader $footerLoader
    ) {
    }

    #[Route(path: '/esi/header', name: 'frontend.esi.header', defaults: ['_httpCache' => true, '_esi' => true], methods: ['GET'])]
    public function header(Request $request, SalesChannelContext $context): Response
    {
        $header = $this->headerLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/esi/header.html.twig', [
            'header' => $header,
        ]);
    }

    #[Route(path: '/esi/footer', name: 'frontend.esi.footer', defaults: ['_httpCache' => true, '_esi' => true], methods: ['GET'])]
    public function footer(Request $request, SalesChannelContext $context): Response
    {
        $footer = $this->footerLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/esi/footer.html.twig', ['footer' => $footer]);
    }
}
