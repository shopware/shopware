<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class ContentRoute extends AbstractContentRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentRouteLoader $contentRouteLoader,
        private readonly RenderingSpecificationResolver $specificationResolver,
    ) {
    }

    public function getDecorated(): AbstractContentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content/{path}',
        name: 'store-api.content.detail',
        requirements: ['path' => '.+'],
        defaults: [
            '_httpCache' => true,
            'excludes' => [
                'content_element' => [
                    'dataRequirements',
                    'contextDefinitions',
                ],
            ],
        ],
        methods: ['GET']
    )]
    public function load(string $path, Request $request, SalesChannelContext $context): ContentRouteResponse
    {
        $renderingSpecification = $this->specificationResolver->resolve($path, $request, $context);
        $contentPage = $this->contentRouteLoader->load($renderingSpecification, RenderingMode::FULL, $context);

        return new ContentRouteResponse($contentPage);
    }
}
