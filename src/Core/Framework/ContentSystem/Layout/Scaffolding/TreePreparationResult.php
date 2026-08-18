<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Scaffolding;

use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\Log\Package;

/**
 * Everything {@see StoredTreePreparer} established, handed over in one value so the caller never
 * re-derives a fact the preparation already settled.
 *
 * `$prePruneForest` is the wrapped forest as it stood before the partial prune, and it is load-bearing
 * rather than a leftover: wiring validation must judge that forest, because a defect inside an element a
 * partial render discards still has to fail the render. Reading `$tree` instead would silently stop
 * reporting those defects, so the field is not unused — its one reader is the validation pass.
 *
 * @internal
 */
#[Package('framework')]
final readonly class TreePreparationResult
{
    /**
     * @param list<StoredElement> $tree the post-prune forest, what gets lowered and rendered
     * @param list<StoredElement> $prePruneForest the wrapped forest before the partial prune, what wiring validation judges
     */
    public function __construct(
        public array $tree,
        public array $prePruneForest,
        public RenderScaffolding $scaffolding,
    ) {
    }
}
