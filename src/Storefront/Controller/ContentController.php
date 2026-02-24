<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\SalesChannel\AbstractContentRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontContentRouteLoader;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
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

    public function index(string $contentSystemEntityId, Request $request, SalesChannelContext $context): Response
    {
        $pathPrefix = $request->attributes->getString(StorefrontContentRouteLoader::ATTRIBUTE_PATH_PREFIX);
        $path = $pathPrefix . $contentSystemEntityId;
        $response = $this->contentRoute->load($path, $request, $context);

        /** @var ContentPage $contentPage */
        $contentPage = $response->getObject();

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', [
            'contentPage' => $contentPage,
        ]);
    }
}
