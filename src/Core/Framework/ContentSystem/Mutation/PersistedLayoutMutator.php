<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mapping\DefaultMappingSeeder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;

/**
 * Applies one structural mutation to a stored content_layout and commits it; the persisted counterpart
 * to {@see MutationPipeline}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class PersistedLayoutMutator
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly LayoutDiagnostics $diagnostics,
        private readonly DefaultMappingSeeder $defaultMappingSeeder,
    ) {
    }

    public function mutate(string $layoutId, ?string $expectedVersion, LayoutMutation $mutation, Context $context): MutationResult
    {
        // Serialize concurrent writers for this layout id so the load → versionMatches → update span is atomic:
        // a second writer blocks here, then re-reads the now-bumped updatedAt and fails versionMatches with a 409
        // instead of silently clobbering the first edit (the lost-update window the optimistic token alone leaves open).
        $lock = $this->lockFactory->createLock('content-layout-mutate-' . $layoutId, 5.0);
        $lock->acquire(true);

        try {
            $layout = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->getEntities()->first();

            if (!$layout instanceof ContentLayoutEntity) {
                throw ContentSystemException::contentLayoutNotFound($layoutId);
            }

            if (!$this->versionMatches($expectedVersion, $layout->getUpdatedAt())) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            // The entity holds the storage model the operations speak, so the loaded tree goes in as it is and the
            // mutated one is handed to the write path the same way: the layout field's serializer takes stored
            // elements directly.
            $mutated = $mutation->apply(new StoredTree($layout->getLayout(), $layout->getRootSource()));
            $mutated = $this->defaultMappingSeeder->seed($mutated, $mutation->freshlyScaffolded());

            $this->contentLayoutRepository->update([[
                'id' => $layoutId,
                'layout' => $mutated->roots,
            ]], $context);

            $analysis = $this->diagnose($mutated, $context);

            return MutationResult::fromAnalyzedMutation($mutated, $analysis, $mutation);
        } finally {
            $lock->release();
        }
    }

    private function versionMatches(?string $expectedVersion, ?\DateTimeInterface $updatedAt): bool
    {
        if ($expectedVersion === null) {
            return $updatedAt === null;
        }

        if ($updatedAt === null) {
            return false;
        }

        try {
            $expected = new \DateTimeImmutable($expectedVersion);
        } catch (\Exception) {
            throw ContentSystemException::invalidVersionToken($expectedVersion);
        }

        // Compare at the storage precision: content_layout.updated_at is DATETIME(3) (millisecond) and the Admin
        // API serializes updatedAt at millisecond precision too, so comparing seconds + milliseconds (not
        // microseconds) keeps the token robust to sub-millisecond noise from either side. getTimestamp() is
        // timezone-independent, so the comparison holds across timezone representations of the same instant.
        return $expected->getTimestamp() === $updatedAt->getTimestamp()
            && $expected->format('v') === $updatedAt->format('v');
    }

    /**
     * Re-resolves the mutated tree against its root source, initialized from the locked layout and preserved by
     * every transform. A bound source supplies a list ([] for none/header/footer), enabling binding-scope checks.
     *
     * A de-registered source is rejected by ContentLayoutWriteValidator during update(), before commit;
     * this later lookup therefore resolves the same admitted root source carried by the mutated tree.
     */
    private function diagnose(StoredTree $tree, Context $context): LayoutAnalysis
    {
        $rootContext = $this->rootSourceRegistry->resolveGated($tree->rootSource, $context);

        return $this->diagnostics->analyze($tree->roots, $rootContext, $tree->rootSource);
    }
}
