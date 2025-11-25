<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationFactoryInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns content data and assignments without skeleton structure.
 *
 * Runs full pipeline (including hydration) but returns only the data
 * and element-to-property assignments. Useful when client already has
 * the skeleton and only needs refreshed data.
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class ContentDataRoute extends AbstractContentDataRoute
{
    /**
     * @param iterable<RenderingSpecificationFactoryInterface> $renderingSpecificationFactories
     *
     * @internal
     */
    public function __construct(
        private readonly ContentRouteLoader $contentRouteLoader,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly iterable $renderingSpecificationFactories,
    ) {
    }

    public function getDecorated(): AbstractContentDataRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-data/{path}',
        name: 'store-api.content.data',
        requirements: ['path' => '.+'],
        defaults: [
            '_httpCache' => true,
        ],
        methods: ['GET']
    )]
    public function load(string $path, Request $request, SalesChannelContext $context): ContentDataRouteResponse
    {
        // Try factories in priority order via Chain of Responsibility
        $renderingSpecification = null;
        foreach ($this->renderingSpecificationFactories as $factory) {
            $renderingSpecification = $factory->create($path, $request, $context);

            if ($renderingSpecification !== null) {
                break;
            }
        }

        if ($renderingSpecification === null) {
            throw ContentSystemException::noFactoryCanHandle($path);
        }

        $contentPage = $this->contentRouteLoader->load($renderingSpecification, RenderingMode::FULL, $context);

        return new ContentDataRouteResponse($contentPage->getContentDataPage($this->configSerializerProvider));
    }
}
