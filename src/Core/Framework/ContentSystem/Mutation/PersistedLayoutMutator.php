<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mutation;

use Shopware\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutAnalysis;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mapping\DefaultMappingSeeder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * Applies one structural mutation to a stored content_layout, either to its live tree or to the head of one of
 * its drafts; the persisted counterpart to {@see MutationPipeline}.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
readonly class PersistedLayoutMutator
{
    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private LayoutRowLock $rowLock,
        private EntityRepository $contentLayoutRepository,
        private RootSourceRegistry $rootSourceRegistry,
        private LayoutDiagnostics $diagnostics,
        private DefaultMappingSeeder $defaultMappingSeeder,
        private MutationPipeline $pipeline,
        private LayoutRevisionService $revisions,
    ) {
    }

    public function mutate(string $layoutId, ?string $expectedVersion, LayoutMutation $mutation, Context $context): MutationResult
    {
        $writePrivilege = $this->checkWritePrivilege($mutation, $context);

        return $this->rowLock->run($layoutId, function () use ($layoutId, $expectedVersion, $mutation, $writePrivilege, $context): MutationResult {
            $layout = $this->load($layoutId, $context);

            if (!$this->versionMatches($expectedVersion, $layout->getUpdatedAt())) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            // The entity holds the storage model the operations speak, so the loaded tree goes in as it is and the
            // mutated one is handed to the write path the same way: the layout field's serializer takes stored
            // elements directly.
            $mutated = $mutation->apply(new StoredTree($layout->getLayout(), $layout->getRootSource()));
            $mutated = $this->defaultMappingSeeder->seed($mutated, $mutation->freshlyScaffolded());

            $this->commit($layoutId, $mutated, $writePrivilege !== null, $context);

            $analysis = $this->diagnose($mutated, $context);

            return MutationResult::fromAnalyzedMutation($mutated, $analysis, $mutation);
        });
    }

    /**
     * Applies the mutation to the head of a draft and appends the result as one revision; the live layout is not
     * written. The mutation runs the draft pipeline, consumer mirroring included, and its result is admitted for
     * well-formedness only, so only publishing enforces resolvability.
     */
    public function mutateDraft(string $layoutId, string $draftId, string $expectedHeadRevisionId, LayoutMutation $mutation, Context $context): DraftMutationResult
    {
        $this->checkWritePrivilege($mutation, $context);

        $result = null;
        $appended = $this->revisions->appendRevision(
            $layoutId,
            $draftId,
            $expectedHeadRevisionId,
            function (array $head) use ($layoutId, $mutation, $context, &$result): StoredTree {
                $rootSource = $this->load($layoutId, $context)->getRootSource();
                $rootContext = $this->rootSourceRegistry->resolveGated($rootSource, $context);
                $result = $this->pipeline->run($mutation, new StoredTree($head, $rootSource), $rootContext);

                return $result->layout;
            },
            $context,
        );

        \assert($result instanceof MutationResult);

        return new DraftMutationResult($result, $appended['revision'], $appended['draft']);
    }

    /**
     * A declared write privilege lets the write run in system scope, where the DAL checks no privilege, so it is
     * checked before anything else: a caller lacking it gets the 403 ahead of any read, 404, 409 or 400.
     */
    private function checkWritePrivilege(LayoutMutation $mutation, Context $context): ?string
    {
        $writePrivilege = $mutation->writePrivilege();

        if ($writePrivilege !== null && !$context->isAllowed($writePrivilege)) {
            throw ContentSystemException::missingPrivileges([$writePrivilege]);
        }

        return $writePrivilege;
    }

    private function load(string $layoutId, Context $context): ContentLayoutEntity
    {
        $layout = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->getEntities()->first();

        if (!$layout instanceof ContentLayoutEntity) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        return $layout;
    }

    /**
     * Scopes the write alone: {@see Context::scope()} switches the scope of the very context it is called on, so a
     * context scoped by the caller would also carry the layout load and the diagnosis.
     */
    private function commit(string $layoutId, StoredTree $mutated, bool $systemScope, Context $context): void
    {
        $data = [[
            'id' => $layoutId,
            'layout' => $mutated->roots,
        ]];

        if (!$systemScope) {
            $this->contentLayoutRepository->update($data, $context);

            return;
        }

        $context->scope(Context::SYSTEM_SCOPE, fn (Context $scoped): EntityWrittenContainerEvent => $this->contentLayoutRepository->update($data, $scoped));
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
