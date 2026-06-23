<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * The shared mutation pipeline: apply one structural transform to an already-decoded layout tree, run the
 * diagnostics pass on the whole new tree, and assemble a {@see MutationResult}. Decoding the raw request layout
 * into the tree is the caller's concern (the admin actions decode through the shared request decoder); the
 * pipeline is agnostic to whether the tree came from a request draft or a loaded content_layout.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class MutationPipeline
{
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * @param list<ContentElement> $tree the decoded draft tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function run(LayoutMutation $mutation, array $tree, ?array $rootContext, ?Context $context = null): MutationResult
    {
        $mutated = $mutation->apply($tree);
        $affected = $mutation->affected();

        $analysis = $this->diagnostics->analyze($mutated, $rootContext, $context);

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
