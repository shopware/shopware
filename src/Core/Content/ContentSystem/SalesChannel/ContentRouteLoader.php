<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\RefinedLayoutBuilder;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\Output\SubTreeExtractor;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('discovery')]
class ContentRouteLoader
{
    public function __construct(
        private readonly RefinedLayoutBuilder $refinedLayoutBuilder,
        private readonly ContentElementHydrator $hydrationService,
        private readonly SubTreeExtractor $subTreeExtractor
    ) {
    }

    /**
     * Orchestrates the routing-independent content system pipeline to load and render content.
     *
     * The rendering pipeline runs after context creation (which may come from routing or
     * entity-based sources). Pipeline consists of refinement, hydration, and optional
     * partial rendering.
     *
     * @param RenderingSpecification $specification Rendering specification (layout ID, placeholders, target element)
     * @param SalesChannelContext $salesChannelContext Sales channel context for data loading
     *
     * @throws ContentSystemException When refinement fails, hydration fails,
     *                                or requested element ID not found in tree
     *
     * @return ContentPage Fully hydrated content page with element tree and metadata
     */
    public function load(
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentPage {
        // Wrap refinement exceptions with context for debugging. The refinement phase can fail
        // due to: layout entity not found in DB, refiner execution errors, or placeholder
        // resolution issues. Wrapping preserves the original exception as cause while clearly
        // indicating which pipeline phase failed (refinement vs routing, hydration, etc.).
        try {
            $refinedLayout = $this->refinedLayoutBuilder->build($specification->layoutId, $specification, $salesChannelContext);
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed($specification->layoutId, $e->getMessage(), $e);
        }

        $this->hydrationService->hydrate($refinedLayout, $salesChannelContext);

        $rootElement = $this->applyPartialRendering(
            $refinedLayout->rootElement,
            $specification
        );

        return new ContentPage(
            layoutId: $specification->layoutId,
            layout: $rootElement,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId()
        );
    }

    private function applyPartialRendering(
        ContentElement $rootElement,
        RenderingSpecification $specification
    ): ContentElement {
        $targetElementId = $specification->targetElementId;
        // Treat empty string as null - query parameters can be empty when ?elementId is
        // present without a value. Both cases mean "no partial rendering requested".
        if ($targetElementId === null || $targetElementId === '') {
            return $rootElement;
        }

        $targetElement = $this->subTreeExtractor->extract($rootElement, $targetElementId);

        if ($targetElement === null) {
            throw ContentSystemException::elementNotFound($targetElementId);
        }

        return $targetElement;
    }
}
