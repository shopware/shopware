<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mapping\DefaultMappingSeeder;
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
        private readonly ContextConsumerMirror $contextMirror,
        private readonly DefaultMappingSeeder $defaultMappingSeeder,
    ) {
    }

    /**
     * @param StoredTree $tree the decoded draft tree
     * @param list<ProvidedContext>|null $rootContext the bound source's root-ambient context, or null when none is bound
     */
    public function run(LayoutMutation $mutation, StoredTree $tree, ?array $rootContext): MutationResult
    {
        $mutated = $mutation->apply($tree);
        $scaffoldedIds = $mutation->freshlyScaffolded();
        $mutated = $this->defaultMappingSeeder->seed($mutated, $scaffoldedIds);

        $analysis = $this->diagnostics->analyze($mutated->roots, $rootContext, $mutated->rootSource);

        $wired = $this->contextMirror->apply($mutated, $analysis->resolutions, $scaffoldedIds);

        // Re-analyze only when the wiring changed the tree, so the returned diagnostics and resolutions describe the returned tree.
        if ($wired !== $mutated) {
            $analysis = $this->diagnostics->analyze($wired->roots, $rootContext, $wired->rootSource);
        }

        return MutationResult::fromAnalyzedMutation($wired, $analysis, $mutation);
    }
}
