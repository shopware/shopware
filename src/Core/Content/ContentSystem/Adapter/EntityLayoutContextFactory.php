<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignableDefinitionInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestParameterExtractor;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationFactoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generic factory for entity-based content layout rendering.
 *
 * Instantiated multiple times via DI with different repository/definition configurations
 * to create Product, Category, and Landing Page specific factories using the same logic.
 *
 * @template TEntityCollection of CategoryContentLayoutCollection|ProductContentLayoutCollection|LandingPageContentLayoutCollection
 *
 * @internal
 */
#[Package('discovery')]
class EntityLayoutContextFactory implements RenderingSpecificationFactoryInterface
{
    /**
     * @param EntityRepository<TEntityCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly ContentLayoutAssignableDefinitionInterface $definition,
        private readonly EntityLayoutBuilder $layoutResolver,
        private readonly RequestParameterExtractor $requestParameterExtractor
    ) {
    }

    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification
    {
        $path = '/' . ltrim($path, '/');
        $pathPrefix = $this->definition->getContentLayoutPathPrefix();

        if (!str_starts_with($path, $pathPrefix)) {
            return null;
        }

        $routePattern = $this->definition->getContentLayoutRoutePattern();
        $route = new Route($pathPrefix . $routePattern);
        $collection = new RouteCollection();
        $collection->add('entity', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            $entityType = $this->definition->getContentLayoutEntityType();
            throw match ($entityType) {
                'product' => ContentSystemException::invalidProductPath($path),
                'category' => ContentSystemException::invalidCategoryPath($path),
                'landing_page' => ContentSystemException::invalidLandingPagePath($path),
                default => ContentSystemException::noFactoryCanHandle($path),
            };
        }

        $entityIdField = $this->definition->getContentLayoutEntityIdField();
        $entityId = $parameters[$entityIdField];

        ['layoutId' => $layoutId, 'placeholderValues' => $placeholderValues] = $this->layoutResolver->resolve(
            $this->repository,
            $this->definition,
            $entityId,
            $request,
            $context
        );

        $targetElementId = $this->requestParameterExtractor->extractTargetElementId($request);

        $dataRequirements = $this->definition->getPageDataRequirements($context);

        return new RenderingSpecification(
            layoutId: $layoutId,
            dataRequirements: $dataRequirements,
            placeholderValues: $placeholderValues,
            targetElementId: $targetElementId
        );
    }
}
