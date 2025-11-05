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
     * Loads and renders content from a specification.
     *
     * @param RenderingSpecification $specification Layout and placeholder configuration
     * @param SalesChannelContext $salesChannelContext Context for data loading
     *
     * @throws ContentSystemException On pipeline failures (refinement, hydration, element lookup)
     *
     * @return ContentPage Hydrated content page
     */
    public function load(
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentPage {
        // Wrap refinement exceptions to indicate pipeline phase (refinement vs routing/hydration)
        try {
            $refinedLayout = $this->refinedLayoutBuilder->build($specification->layoutId, $specification, $salesChannelContext);
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed($specification->layoutId, $e->getMessage(), $e);
        }

        $hydratedElements = $this->hydrationService->hydrate($refinedLayout->elements, $salesChannelContext);

        $elements = $this->applyPartialRendering($hydratedElements, $specification);

        return new ContentPage(
            layoutId: $specification->layoutId,
            elements: $elements,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId()
        );
    }

    /**
     * @param iterable<ContentElement> $elements
     *
     * @return \Generator<ContentElement>
     */
    private function applyPartialRendering(
        iterable $elements,
        RenderingSpecification $specification
    ): \Generator {
        $targetElementId = $specification->targetElementId;
        // Treat empty string as null - query parameters can be empty when ?elementId is
        // present without a value. Both cases mean "no partial rendering requested".
        if ($targetElementId === null || $targetElementId === '') {
            yield from $elements;

            return;
        }

        foreach ($elements as $element) {
            $targetElement = $this->subTreeExtractor->extract($element, $targetElementId);
            if ($targetElement !== null) {
                yield $targetElement;

                return;
            }
        }

        throw ContentSystemException::elementNotFound($targetElementId);
    }
}
