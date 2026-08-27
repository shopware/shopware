<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
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
        private readonly PageContextConsumerWiring $contextWiring,
    ) {
    }

    /**
     * @param StoredTree $tree the decoded draft tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null for the well-formedness subset
     */
    public function run(LayoutMutation $mutation, StoredTree $tree, ?array $rootContext): MutationResult
    {
        $mutated = $mutation->apply($tree);

        $analysis = $this->diagnostics->analyze($mutated->roots, $rootContext);

        // Wire the page-context consumers into the mutated tree from the analysis, so the returned (and, on the
        // persisted route, stored) layout carries the distribution wiring every consumer needs.
        $mutated = $this->contextWiring->apply($mutated, $analysis->resolutions, $rootContext ?? []);

        return MutationResult::fromAnalyzedMutation($mutated, $analysis, $mutation);
    }
}
