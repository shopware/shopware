<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Binding\LayoutBindingEnumerator;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Diagnostics\ViolationScope;
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
 * Applies one structural mutation to a stored content_layout and commits it. Loads the layout by id, guards an
 * optimistic-concurrency token, applies the operation to the loaded tree, re-resolves against the layout's real
 * source bindings, and persists the mutated tree (whose write runs the resolvability gates). The persisted
 * counterpart to {@see MutationPipeline}, which transforms a stateless draft tree without touching storage.
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
     * @param iterable<LayoutBindingEnumerator> $bindingEnumerators
     */
    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly ContentElementFieldSerializer $elementSerializer,
        private readonly iterable $bindingEnumerators,
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

            $analysis = $this->diagnose($layoutId, $mutated, $context);

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
     * Re-resolves the mutated tree against the layout's real source bindings (the same enumerators the write gate
     * uses), so the echoed report matches what the gate enforced on commit. An unbound layout is diagnosed for
     * well-formedness only. A layout bound to several sources unions every binding-scope violation into one report;
     * the resolutions reflect the last bound source diagnosed.
     *
     * @param list<ContentElement> $tree
     */
    private function diagnose(string $layoutId, array $tree, Context $context): LayoutAnalysis
    {
        $bindings = [];
        foreach ($this->bindingEnumerators as $enumerator) {
            foreach ($enumerator->enumerate($layoutId, $context) as $binding) {
                $bindings[] = $binding;
            }
        }

        if ($bindings === []) {
            return $this->diagnostics->analyze($tree, null, $context);
        }

        $intrinsic = [];
        $bindingViolations = [];
        $resolutions = [];

        foreach ($bindings as $index => $binding) {
            $analysis = $this->diagnostics->analyze($tree, $binding->providedRootContext, $context);
            $resolutions = $analysis->resolutions;

            foreach ($analysis->report->violations as $violation) {
                if ($violation->scope() === ViolationScope::Intrinsic) {
                    if ($index === 0) {
                        $intrinsic[] = $violation;
                    }

                    continue;
                }

                $bindingViolations[$this->violationKey($violation)] = $violation;
            }
        }

        return new LayoutAnalysis(new DiagnosticsReport([...$intrinsic, ...array_values($bindingViolations)]), $resolutions);
    }

    private function violationKey(Violation $violation): string
    {
        return $violation->code->value . '|' . $violation->elementId . '|' . ($violation->key ?? '');
    }
}
