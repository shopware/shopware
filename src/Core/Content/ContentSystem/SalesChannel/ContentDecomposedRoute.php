<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
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
class ContentDecomposedRoute extends AbstractContentDecomposedRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentRouteLoader $contentRouteLoader,
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider
    ) {
    }

    public function getDecorated(): AbstractContentDecomposedRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-decomposed/{path}',
        name: 'store-api.content.decomposed',
        requirements: ['path' => '.+'],
        defaults: [
            '_httpCache' => true,
            'excludes' => [
                'content_element' => [
                    'dataRequirements',
                    'properties',
                    'contextDefinitions',
                ],
            ],
        ],
        methods: ['GET']
    )]
    public function load(string $path, Request $request, SalesChannelContext $context): ContentDecomposedRouteResponse
    {
        $renderingSpecification = $this->specificationResolver->resolve($path, $request, $context);
        $contentPage = $this->contentRouteLoader->load($renderingSpecification, RenderingMode::FULL, $context);

        return new ContentDecomposedRouteResponse($contentPage->getContentDecomposedPage($this->configSerializerProvider));
    }
}
