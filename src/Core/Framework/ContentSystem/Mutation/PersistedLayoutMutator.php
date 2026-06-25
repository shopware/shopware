<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;

/**
 * Applies one structural mutation to a stored content_layout and commits it; the persisted counterpart to
 * {@see MutationPipeline}, which transforms a stateless draft tree without touching storage.
 *
 * Known interim limitations, owned by and deferred to the planned layout draft/versioning system that will
 * supersede this `expectedVersion`/lock concurrency mechanism:
 * - The per-layout lock has a fixed 5.0s TTL (see {@see mutate()}); a critical section that runs longer could let
 *   the lock expire mid-write, reopening the lost-update window the lock closes. The optimistic `updatedAt` token
 *   still narrows that window but does not eliminate it.
 * - If {@see diagnose()} throws after the `update()` has already committed, the caller sees an error even though
 *   the write landed. The committed tree is the source of truth, so a retry re-reads the bumped `updatedAt` and
 *   gets a 409 `layoutVersionConflict`.
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
        private readonly ContentElementFieldSerializer $elementSerializer,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly LayoutDiagnostics $diagnostics,
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
            $layout = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->first();

            if (!$layout instanceof ContentLayoutEntity) {
                throw ContentSystemException::contentLayoutNotFound($layoutId);
            }

            if (!$this->versionMatches($expectedVersion, $layout->getUpdatedAt())) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            $mutated = $mutation->apply($layout->getLayout());
            $affected = $mutation->affected();

            $this->contentLayoutRepository->update([[
                'id' => $layoutId,
                'layout' => array_map($this->elementSerializer->serializeContentElement(...), $mutated),
            ]], $context);

            $analysis = $this->diagnose($layout->getRootSource(), $mutated, $context);

            // This MutationResult assembly is intentionally duplicated in MutationPipeline::run() (see the note
            // there): sharing it would couple Mutation/ to a Diagnostics/LayoutAnalysis-shaped helper or require
            // a banned static helper.
            return new MutationResult(
                $mutated,
                array_intersect_key($analysis->resolutions, array_flip($affected)),
                $analysis->report,
                $affected,
                $mutation->orphaned(),
                $mutation->droppedWiring(),
                $mutation->droppedProperties(),
            );
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
     * Re-resolves the mutated tree against the layout's single root source (the loaded entity carries it), so the
     * echoed report matches what the content_layout write gate enforced on commit. resolve() returns a list (never
     * null — [] for none/header/footer), so the binding-scope checks always run; the intrinsic-only path no longer
     * applies to a stored layout.
     *
     * resolve() is never handed an unregistered id here even when the stored source was de-registered: mutate()
     * commits the tree via update() first, and that write runs ContentLayoutWriteValidator, which re-checks
     * membership of the committed root source and rejects a de-registered source as a clean unknownRootSource 400
     * before any commit. A membership gate in this method would instead fire after the commit (the post-commit
     * diagnose() limitation noted on the class), so the preceding write gate is the correct and only check needed.
     *
     * @param list<ContentElement> $tree
     */
    private function diagnose(string $rootSource, array $tree, Context $context): LayoutAnalysis
    {
        return $this->diagnostics->analyze($tree, $this->rootSourceRegistry->resolve($rootSource, $context), $context);
    }
}
