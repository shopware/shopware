<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
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
     * @throws ContentSystemException When layout not found or pipeline phase fails
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

        $scaffoldedLayout = $this->scaffoldingProcessor->scaffold(
            $layoutEntity,
            $specification,
            $salesChannelContext
        );

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

        $hydratedElements = iterator_to_array(
            $this->hydrationService->hydrate(
                iterator_to_array($refinedLayout->elements, false),
                $salesChannelContext,
                $specification->request
            ),
            false
        );

        $hydratedLayout = clone $scaffoldedLayout;
        $hydratedLayout->setLayout($hydratedElements);

        $finalLayout = $this->scaffoldingProcessor->dismantle(
            $hydratedLayout,
            $specification,
            $salesChannelContext
        );

        $outputElements = $this->extractPartialRenderingTarget(
            $finalLayout->getLayout(),
            $specification->targetElementId
        );

        return new ContentPage(
            layoutId: $specification->layoutId,
            elements: $outputElements,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId()
        );
    }

    /**
     * Extracts target element + descendants for partial rendering
     * (removes parent elements that were kept for context distribution)
     *
     * @param array<ContentElement> $elements
     *
     * @throws ContentSystemException If target element not found
     *
     * @return array<ContentElement>
     */
    private function extractPartialRenderingTarget(array $elements, ?string $targetElementId): array
    {
        if ($targetElementId === null || $targetElementId === '') {
            return $elements;
        }

        foreach ($elements as $element) {
            $targetElement = $this->subTreeExtractor->extract($element, $targetElementId);
            if ($targetElement !== null) {
                return [$targetElement];
            }
        }

        throw ContentSystemException::elementNotFound($targetElementId);
    }
}
