<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Event;

use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Scaffolding\StoredTreePreparer;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Dispatched at the head of `ContentPipeline::load()`, over the stored forest as it was loaded.
 *
 * Listeners see raw author content: no placeholder has been resolved and no structural step has run.
 * The dispatch position is the same in both rendering modes, and every pipeline step runs after it, so
 * a listener at any priority sees the same tree — core claims no priority band.
 *
 * A listener replaces the forest rather than editing it: a stored element is immutable, so an edit
 * produces new instances that only `replaceTree()` can put back.
 *
 * @final
 */
#[Package('framework')]
class ContentTreePreparationEvent implements ShopwareSalesChannelEvent
{
    /**
     * @param list<StoredElement> $tree
     */
    public function __construct(
        private array $tree,
        public readonly LayoutReference $layout,
        public readonly RenderingSpecification $specification,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly RenderingCacheContext $cacheContext,
    ) {
        $this->rejectForeignTree($tree);
    }

    /**
     * @return list<StoredElement>
     */
    public function tree(): array
    {
        return $this->tree;
    }

    /**
     * @param list<StoredElement> $tree
     */
    public function replaceTree(array $tree): void
    {
        $this->rejectForeignTree($tree);

        $this->tree = $tree;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * The `list<StoredElement>` the signature can only promise in a docblock. A listener that hands the
     * rendered model back instead reaches the preparation steps: the FULL path dies on the parameter type of a
     * closure inside {@see StoredTreePreparer}, and the SKELETON path walks on to read `contextDefinitions` off
     * an element that declares none, so what gets reported names a core internal rather than the listener that
     * caused it. Depth needs no walk: every {@see StoredElement} refuses a foreign slot child, so a list of
     * stored roots is a stored forest.
     *
     * @param array<array-key, mixed> $tree
     */
    private function rejectForeignTree(array $tree): void
    {
        if (!array_is_list($tree)) {
            throw ContentSystemException::invalidMapValue(
                'Stored content tree',
                'tree',
                'list<StoredElement>',
                'array with non-list keys'
            );
        }

        foreach ($tree as $index => $element) {
            if ($element instanceof StoredElement) {
                continue;
            }

            throw ContentSystemException::invalidMapValue(
                'Stored content tree',
                (string) $index,
                StoredElement::class,
                get_debug_type($element)
            );
        }
    }
}
