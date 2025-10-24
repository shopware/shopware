<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationFactoryInterface;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
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
class LandingPageContextFactory implements RenderingSpecificationFactoryInterface
{
    /**
     * @param EntityRepository<LandingPageContentLayoutCollection> $landingPageContentLayoutRepository
     */
    public function __construct(
        private readonly EntityRepository $landingPageContentLayoutRepository
    ) {
    }

    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification
    {
        $path = '/' . ltrim($path, '/');
        if (!str_starts_with($path, '/landing-page/')) {
            return null; // Chain of Responsibility: Let next factory try
        }

        // Responsibility handoff: This factory owns /landing-page/* paths and must handle or throw
        $route = new Route('/landing-page/{landingPageId}');
        $collection = new RouteCollection();
        $collection->add('landing_page', $route);

        $requestContext = new RequestContext();
        $requestContext->setPathInfo($path);

        $matcher = new UrlMatcher($collection, $requestContext);

        try {
            $parameters = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            throw ContentSystemException::invalidLandingPagePath($path);
        }

        $landingPageId = $parameters['landingPageId'];
        $layoutId = LayoutSearchHelper::buildLayoutIdSearchCriteria(
            'landingPageId',
            $landingPageId,
            $context,
            $this->landingPageContentLayoutRepository
        );

        if ($layoutId === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'landing_page',
                $landingPageId,
                $context->getSalesChannel()->getId()
            );
        }

        $placeholderValues = PlaceholderValues::from([
            'landingPageId' => $landingPageId,
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
