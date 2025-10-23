<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
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
 * @internal
 */
#[Package('discovery')]
class CategoryContextFactory implements RenderingSpecificationFactoryInterface
{
    /**
     * @param EntityRepository<CategoryContentLayoutCollection> $categoryContentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryContentLayoutRepository
    ) {
    }

    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification
    {
        $path = '/' . ltrim($path, '/');
        if (!str_starts_with($path, '/category/')) {
            return null; // Chain of Responsibility: Let next factory try
        }

        // Responsibility handoff: This factory owns /category/* paths and must handle or throw
        $route = new Route('/category/{categoryId}');
        $collection = new RouteCollection();
        $collection->add('category', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            throw ContentSystemException::invalidCategoryPath($path);
        }

        $categoryId = $parameters['categoryId'];
        $layoutId = LayoutSearchHelper::buildLayoutIdSearchCriteria(
            'categoryId',
            $categoryId,
            $context,
            $this->categoryContentLayoutRepository
        );

        if ($layoutId === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'category',
                $categoryId,
                $context->getSalesChannel()->getId()
            );
        }

        $placeholderValues = PlaceholderValues::from([
            'categoryId' => $categoryId,
        ]);

        $targetElementId = $request->query->get('elementId');
        if ($targetElementId !== null && !\is_string($targetElementId)) {
            throw ContentSystemException::invalidElementId();
        }

        return new RenderingSpecification(
            layoutId: $layoutId,
            placeholderValues: $placeholderValues,
            targetElementId: $targetElementId
        );
    }
}
