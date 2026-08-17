<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElementLowering;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class MutationPipeline
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
        private readonly ContentElementLowering $lowering,
    ) {
    }

    /**
     * @param StoredTree $tree the decoded draft tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function run(LayoutMutation $mutation, StoredTree $tree, ?array $rootContext): MutationResult
    {
        $mutated = $mutation->apply($tree);
        $affected = $mutation->affected();

        // The diagnostics pass still speaks the older element model, so the mutated tree is lowered on the way
        // out; the operations themselves never leave the storage model.
        $analysis = $this->diagnostics->analyze($this->lowering->lowerTree($mutated->roots), $rootContext);

        // This MutationResult assembly is intentionally duplicated in PersistedLayoutMutator::mutate(): sharing it
        // would couple Mutation/ to a Diagnostics/LayoutAnalysis-shaped helper or require a banned static helper,
        // so each runner assembles its own result from its own analysis.
        return new MutationResult(
            $mutated,
            array_intersect_key($analysis->resolutions, array_flip($affected)),
            $analysis->report,
            $affected,
            $mutation->orphaned(),
            $mutation->droppedWiring(),
            $mutation->droppedProperties(),
        );
    }
}
