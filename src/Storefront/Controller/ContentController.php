<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('discovery')]
class ContentController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContentRoute $contentRoute
    ) {
    }

    #[Route(
        path: '/{path}',
        name: 'frontend.content.page',
        requirements: ['path' => '.+'],
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
        priority: -100
    )]
    public function index(Request $request, SalesChannelContext $context): Response
    {
        $pathInfo = $request->getPathInfo();
        $response = $this->contentRoute->load($pathInfo, $request, $context);

        /** @var \Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage $contentPage */
        $contentPage = $response->getObject();

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', [
            'contentPage' => $contentPage,
        ]);
    }
}
