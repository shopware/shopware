<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Loader\LayoutLoader;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\RefinedLayoutBuilder;
use Shopware\Core\Content\ContentSystem\Layout\Scaffolding\ScaffoldingProcessor;
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
        private readonly LayoutLoader $layoutLoader,
        private readonly ScaffoldingProcessor $scaffoldingProcessor,
        private readonly RefinedLayoutBuilder $refinedLayoutBuilder,
        private readonly ContentElementHydrator $hydrationService,
        private readonly SubTreeExtractor $subTreeExtractor
    ) {
    }

    /**
     * Loads and renders content from a specification.
     *
     * Pipeline: Load → Scaffold → Refine → Hydrate → Dismantle → Extract → Response
     *
     * @param RenderingSpecification $specification Layout and placeholder configuration
     * @param SalesChannelContext $salesChannelContext Context for data loading
     *
     * @throws ContentSystemException On pipeline failures (layout not found, refinement, hydration)
     *
     * @return ContentPage Hydrated content page
     */
    public function load(
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentPage {
        // 1. Load layout entity
        $layoutEntity = $this->layoutLoader->load(
            $specification->layoutId,
            $salesChannelContext->getContext()
        );

        // 2. Apply scaffolding (virtual root wrapping only - no partial rendering)
        $scaffoldedLayout = $this->scaffoldingProcessor->scaffold(
            $layoutEntity,
            $specification,
            $salesChannelContext
        );

        // 3. Refine scaffolded layout (PartialRenderingRefiner prunes tree with context preservation if ?elementId)
        try {
            $refinedLayout = $this->refinedLayoutBuilder->refine(
                $scaffoldedLayout,
                $specification,
                $salesChannelContext
            );
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed(
                $specification->layoutId,
                $e->getMessage(),
                $e
            );
        }

        // 4. Hydrate refined elements
        $hydratedElements = iterator_to_array(
            $this->hydrationService->hydrate(
                iterator_to_array($refinedLayout->elements, false),
                $salesChannelContext
            ),
            false
        );

        // 5. Restore hydrated elements to layout entity
        $hydratedLayout = clone $scaffoldedLayout;
        $hydratedLayout->setLayout($hydratedElements);

        // 6. Dismantle scaffolding (removes virtual root, restores structure)
        $finalLayout = $this->scaffoldingProcessor->dismantle(
            $hydratedLayout,
            $specification,
            $salesChannelContext
        );

        // 7. Post-hydration extraction for partial rendering (if ?elementId specified)
        $outputElements = $this->extractPartialRenderingTarget(
            $finalLayout->getLayout(),
            $specification->targetElementId
        );

        // 8. Create response
        return new ContentPage(
            layoutId: $specification->layoutId,
            elements: $outputElements,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId()
        );
    }

    /**
     * Extracts target element for partial rendering after hydration.
     *
     * Returns only the target element + descendants, removing any parent elements
     * that were kept during hydration for context distribution.
     *
     * @param array<\Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement> $elements
     *
     * @throws ContentSystemException If target element not found
     *
     * @return array<\Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement>
     */
    private function extractPartialRenderingTarget(array $elements, ?string $targetElementId): array
    {
        // If no partial rendering requested, return all elements
        if ($targetElementId === null || $targetElementId === '') {
            return $elements;
        }

        // Search through roots for target element
        foreach ($elements as $element) {
            $targetElement = $this->subTreeExtractor->extract($element, $targetElementId);
            if ($targetElement !== null) {
                return [$targetElement];
            }
        }

        // Target element not found
        throw ContentSystemException::elementNotFound($targetElementId);
    }
}
