<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns content layout skeleton without hydrated data.
 *
 * Runs pre-hydration events (placeholder resolution, partial rendering prep)
 * but skips hydration. Useful for layout preview or client-side hydration.
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class ContentSkeletonRoute extends AbstractContentSkeletonRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentPipeline $contentPipeline,
    ) {
    }

    public function getDecorated(): AbstractContentSkeletonRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-skeleton/{path}',
        name: 'store-api.content.skeleton',
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
    public function load(string $path, Request $request, SalesChannelContext $context): ContentSkeletonRouteResponse
    {
        $renderingSpecification = $this->specificationResolver->resolve($path, $request, $context);
        $contentPage = $this->contentPipeline->load($renderingSpecification, RenderingMode::SKELETON, $context);

        return new ContentSkeletonRouteResponse($contentPage->getContentSkeletonPage());
    }
}
