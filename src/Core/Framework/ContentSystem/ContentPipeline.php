<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Shopware\Core\Framework\ContentSystem\Event\ContentTreePreparationEvent;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\RenderScaffolding;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndexFactory;
use Shopware\Core\Framework\ContentSystem\Output\PartialRenderer;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\ElementLowering;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\Rendering\WiringPlanner;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentPipeline
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StoredTreePreparer $storedTreePreparer,
        private readonly WiringPlanner $wiringPlanner,
        private readonly ElementLowering $elementLowering,
        private readonly VirtualRootWrapper $virtualRootWrapper,
        private readonly PartialRenderer $partialRenderer,
        private readonly ResolvedValueIndexFactory $indexFactory,
    ) {
    }

    /**
     * @param bool $collectValueIndex whether the response format rebuilds its body from the
     *                                {@see ResolvedValueIndex} instead of serving property values inline. The
     *                                index is finalized against the FINISHED tree — after the finishing steps
     *                                and the finalization event — so a node a partial extract dropped and a
     *                                key a listener rewrote are both accounted for as the response will carry
     *                                them, not as the lowering produced them.
     */
    public function load(
        RenderableLayout $layout,
        RenderingSpecification $specification,
        RenderingCacheContext $cacheContext,
        RenderingMode $mode,
        bool $collectValueIndex,
        SalesChannelContext $salesChannelContext,
    ): RenderResult {
        $preparationEvent = new ContentTreePreparationEvent(
            $layout->elements,
            $layout->reference,
            $specification,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($preparationEvent);

        $preparation = $this->storedTreePreparer->prepare($preparationEvent->tree(), $specification, $mode);
        $scaffolding = $preparation->scaffolding;

        $this->rejectRepeatedStoredId($preparation->prePruneForest);

        $storedTree = $this->wiringPlanner->plan($preparation->prePruneForest, $preparation->tree);

        $lowered = $this->elementLowering->lower(
            $storedTree,
            $mode,
            $salesChannelContext,
            $specification->request,
            $cacheContext,
        );

        $renderedTree = $this->unwrapVirtualRoot($lowered->tree, $scaffolding);
        $renderedTree = $this->extractPartialTarget($renderedTree, $scaffolding);

        $finalizationEvent = new RenderedTreeFinalizationEvent(
            $renderedTree,
            $layout->reference,
            $specification,
            $salesChannelContext,
            $cacheContext,
        );
        $this->eventDispatcher->dispatch($finalizationEvent);

        // The tree the event handed back, not the one it was given, so a listener's replacement is what the
        // result carries and what the index is built over.
        $finishedTree = $finalizationEvent->tree();

        $this->rejectRepeatedRenderedId($finishedTree);

        return new RenderResult(
            $finishedTree,
            $finalizationEvent->layout,
            $collectValueIndex ? $this->indexFactory->create($finishedTree, $lowered->provenance) : null,
        );
    }

    /**
     * Element ids are unique across a forest by contract, and every consumer downstream of here addresses an
     * element by id alone. The stored forest is judged before the lowering, and the pre-prune forest is what
     * gets judged: a partial render's prune drops whole sibling subtrees and the later target extract drops
     * every non-target root, so a twin removed by either would go unreported while the response quietly
     * served one of two ambiguous elements. This is the same stance wiring validation takes on the same
     * forest, for the same reason.
     *
     * A collision with the virtual root is a deliberate throw. The forest handed in here is captured after
     * the virtual-root wrap, so whenever the render wraps it carries the synthetic wrapper element under the
     * reserved id {@see VirtualRootWrapper::VIRTUAL_ROOT_ID} (`__page_context_root__`). A stored element
     * authored under that literal id therefore collides with the wrapper and fails the render.
     *
     * The write gate cannot see that particular collision, which is why it lands at render time: the virtual
     * root is minted during rendering and is never part of a stored tree, so the `StoredTree::validate()` run
     * on the write path only ever sees the authored elements. A layout carrying the reserved id passes every
     * write gate and then fails every render that wraps.
     *
     * @param list<StoredElement> $forest
     */
    private function rejectRepeatedStoredId(array $forest): void
    {
        foreach ((new StoredTree($forest))->validate() as $violation) {
            if ($violation->code !== ViolationCode::DuplicateElementId) {
                continue;
            }

            throw ContentSystemException::duplicateElementId($violation->elementId);
        }
    }

    /**
     * The stored check cannot stand in for this one: a finalization listener may hand back a tree of its own,
     * and that replacement is what the result carries, so a duplicate it introduces is invisible to a check
     * that ran before the lowering. {@see StoredTree} is stored-side and cannot hold a rendered element, so
     * the walk is written out here rather than reused.
     *
     * @param list<RenderedElement> $forest
     */
    private function rejectRepeatedRenderedId(array $forest): void
    {
        $seen = [];
        $this->walkRenderedIds($forest, $seen);
    }

    /**
     * @param list<RenderedElement> $elements
     * @param array<string, true> $seen
     */
    private function walkRenderedIds(array $elements, array &$seen): void
    {
        foreach ($elements as $element) {
            if (isset($seen[$element->id])) {
                throw ContentSystemException::duplicateElementId($element->id);
            }

            $seen[$element->id] = true;

            foreach ($element->slots as $children) {
                $this->walkRenderedIds($children, $seen);
            }
        }
    }

    /**
     * Removes the virtual root wrapper, restoring the original layout structure.
     *
     * @param list<RenderedElement> $elements
     *
     * @return list<RenderedElement>
     */
    private function unwrapVirtualRoot(array $elements, RenderScaffolding $scaffolding): array
    {
        if (!$scaffolding->virtualRootSurvivedPrune) {
            return $elements;
        }

        return $this->virtualRootWrapper->unwrap($elements[0]);
    }

    /**
     * Extracts the target element and its descendants for partial rendering.
     *
     * Removes the parent elements that `pruneToTarget()` kept for context distribution.
     *
     * @param list<RenderedElement> $elements
     *
     * @return list<RenderedElement>
     */
    private function extractPartialTarget(array $elements, RenderScaffolding $scaffolding): array
    {
        if ($scaffolding->extractTargetId === null) {
            return $elements;
        }

        return [$this->partialRenderer->extractTarget($elements, $scaffolding->extractTargetId)];
    }
}
