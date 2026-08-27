<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Everything {@see StoredTreePreparer} established, handed over in one value so the caller never
 * re-derives a fact the preparation already settled.
 *
 * `$prePruneForest` is the wrapped forest as it stood before the partial prune, and it is load-bearing
 * rather than a leftover: it has two readers, the wiring validation pass and the duplicate-element-id check,
 * and both must judge that forest rather than `$tree`. A wiring defect inside an element a partial render
 * discards still has to fail the render, and a repeated id whose twin the prune removed still has to fail it
 * too — otherwise the response quietly serves one of two ambiguous elements. Reading `$tree` instead would
 * silently stop reporting either.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class TreePreparationResult
{
    /**
     * @param list<StoredElement> $tree the post-prune forest, what gets lowered and rendered
     * @param list<StoredElement> $prePruneForest the wrapped forest before the partial prune, what wiring validation and the duplicate-element-id check judge
     */
    public function __construct(
        public array $tree,
        public array $prePruneForest,
        public RenderScaffolding $scaffolding,
    ) {
    }
}
