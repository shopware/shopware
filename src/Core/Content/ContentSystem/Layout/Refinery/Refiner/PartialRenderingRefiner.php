<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Refinery\Refiner;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\Context\ContextDependencyAnalyzer;
use Shopware\Core\Content\ContentSystem\Layout\Element\TreeUtil\ElementTreeUtil;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\LayoutRefinerInterface;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Reduces hydration overhead by pruning layout tree to target element and its dependencies.
 *
 * Runs at priority 200 (after placeholder resolution).
 * Only activates when partial rendering is requested via elementId query parameter.
 *
 * @internal
 */
#[Package('discovery')]
class PartialRenderingRefiner implements LayoutRefinerInterface
{
    public function __construct(
        private readonly ElementTreeUtil $treeUtil,
        private readonly ContextDependencyAnalyzer $dependencyAnalyzer,
    ) {
    }

    public function refine(
        ContentElement $layout,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentElement {
        $targetElementId = $specification->targetElementId;
        if ($targetElementId === null || $targetElementId === '') {
            return $layout;
        }

        // Keep context-dependent ancestors + target + descendants to preserve data flow
        return $this->treeUtil->pruneToPathAndDescendants(
            $layout,
            $targetElementId,
            $this->dependencyAnalyzer
        );
    }
}
