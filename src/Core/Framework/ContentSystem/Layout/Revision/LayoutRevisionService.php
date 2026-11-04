<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Drafts of a content layout are branches of its revision graph. Saving appends a revision to a branch,
 * publishing moves the published pointer and writes the revision's tree to the `layout` column through the
 * DAL, which runs the full write gate including resolvability. Every operation, reads included, locks the
 * layout row for its transaction and first reconciles the published pointer with the `layout` column, see
 * {@see reconcile()}; concurrent saves on one layout therefore serialize and the stale one fails its head check.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutRevisionService
{
    private const DEFAULT_BRANCH_NAME = 'Draft';

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LayoutRevisionStore $store,
        private readonly DraftLayoutDecoder $decoder,
        private readonly LayoutWriteBoundary $writeBoundary,
        private readonly StoredTreeCodec $treeCodec,
        private readonly StoredTreeConstraints $treeConstraints,
        private readonly ValidatorInterface $validator,
        private readonly LayoutGate $gate,
        private readonly ViolationConstraintMapper $violationMapper,
        private readonly EntityRepository $contentLayoutRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<LayoutBranch>
     */
    public function listBranches(string $layoutId): array
    {
        return $this->locked($layoutId, function (string $published) use ($layoutId): array {
            $branches = array_values($this->store->graph($layoutId, $published)->branches);

            usort($branches, static fn (LayoutBranch $a, LayoutBranch $b): int => $b->updatedAt <=> $a->updatedAt);

            return $branches;
        });
    }

    /**
     * @return array{branch: LayoutBranch, layout: list<StoredElement>}
     */
    public function getBranch(string $layoutId, string $branchId): array
    {
        return $this->locked($layoutId, function () use ($layoutId, $branchId): array {
            $branch = $this->branch($layoutId, $branchId);

            return ['branch' => $branch, 'layout' => $this->tree($layoutId, $branch->head)];
        });
    }

    public function history(string $layoutId): RevisionGraph
    {
        return $this->locked($layoutId, fn (string $published): RevisionGraph => $this->store->graph($layoutId, $published));
    }

    public function createBranch(string $layoutId, ?string $name = null, ?string $fromRevisionId = null): LayoutBranch
    {
        return $this->locked($layoutId, function (string $published) use ($layoutId, $name, $fromRevisionId): LayoutBranch {
            $base = $fromRevisionId ?? $published;

            if ($this->store->graph($layoutId, $published)->revision($base) === null) {
                throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $base);
            }

            $now = $this->now();
            $branch = new LayoutBranch(Uuid::randomHex(), $name ?? self::DEFAULT_BRANCH_NAME, $base, $base, $published, $now, $now);

            $this->store->saveBranch($layoutId, $branch);

            return $branch;
        });
    }

    /**
     * The layout name is not versioned: a `$name` that differs from the layout's is written to the layout row.
     *
     * @param array<array-key, mixed> $layout
     *
     * @return array{revision: LayoutRevision, branch: LayoutBranch, layout: list<StoredElement>}
     */
    public function saveRevision(string $layoutId, string $branchId, array $layout, string $expectedHead, ?string $name, Context $context): array
    {
        $tree = $this->admit($layout);

        return $this->locked($layoutId, function () use ($layoutId, $branchId, $tree, $expectedHead, $name, $context): array {
            $branch = $this->branch($layoutId, $branchId);

            if ($branch->head !== $expectedHead) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            $now = $this->now();
            $revision = new LayoutRevision(Uuid::randomHex(), $branch->head, $now, $this->userId($context));
            $branch = $branch->withHead($revision->id, $now);

            $this->store->insertRevision($layoutId, $revision, $tree);
            $this->store->saveBranch($layoutId, $branch);

            if ($name !== null) {
                $this->rename($layoutId, $name, $context);
            }

            return ['revision' => $revision, 'branch' => $branch, 'layout' => $tree];
        });
    }

    /**
     * Publishing never replaces a live state its caller has not seen. `$expectedPublished` is the published
     * revision the caller last saw; a draft that is published and deleted in one go must also still be based on
     * the live state it was opened from, or contain the current one. The DAL update and the pointer move share
     * one transaction, so a tree the write gate refuses leaves the published pointer where it was.
     */
    public function publish(string $layoutId, string $revisionId, ?string $deleteBranchId, ?string $expectedPublished, Context $context): void
    {
        $this->locked($layoutId, function (string $published) use ($layoutId, $revisionId, $deleteBranchId, $expectedPublished, $context): void {
            if ($expectedPublished !== null && $expectedPublished !== $published) {
                throw ContentSystemException::layoutPublishConflict($layoutId, $published);
            }

            $tree = $this->tree($layoutId, $revisionId);

            if ($deleteBranchId !== null) {
                $branch = $this->branch($layoutId, $deleteBranchId);

                if ($branch->live !== $published && !$this->store->graph($layoutId, $published)->isAncestorOrSelf($published, $revisionId)) {
                    throw ContentSystemException::layoutPublishConflict($layoutId, $published);
                }

                $this->store->deleteBranch($layoutId, $deleteBranchId);
            }

            $this->contentLayoutRepository->update([['id' => $layoutId, 'layout' => $tree]], $context);
            $this->store->markPublished($layoutId, $revisionId, $this->now());
        });
    }

    public function deleteBranch(string $layoutId, string $branchId): void
    {
        $this->locked($layoutId, function (string $published) use ($layoutId, $branchId): void {
            $this->branch($layoutId, $branchId);
            $this->store->deleteBranch($layoutId, $branchId);

            $this->store->deleteRevisions($layoutId, $this->store->graph($layoutId, $published)->unreachableIds());
        });
    }

    /**
     * The layout write's well-formedness admission without its resolvability step, which only publishing
     * enforces: decode, the write boundary, the stored-tree constraints plus the `NotBlank` the required
     * layout field adds, and the gate's intrinsic errors.
     *
     * @param array<array-key, mixed> $layout
     *
     * @return list<StoredElement>
     */
    private function admit(array $layout): array
    {
        $tree = new StoredTree($this->decoder->decode($layout));

        try {
            $tree = $this->writeBoundary->apply($tree);
        } catch (ContentSystemException $exception) {
            throw ContentSystemException::layoutWriteRejection($exception, 'layout', $layout, '');
        }

        $violations = new ConstraintViolationList();
        $violations->addAll($this->validator->validate($this->treeCodec->encode($tree), [...$this->treeConstraints->build(), new NotBlank()]));
        $violations->addAll($this->violationMapper->toConstraintViolationList(
            $this->gate->wellFormedness($tree->roots)->intrinsicErrors()
        ));

        if ($violations->count() > 0) {
            throw ContentSystemException::invalidLayoutStructure($violations);
        }

        return $tree->roots;
    }

    private function rename(string $layoutId, string $name, Context $context): void
    {
        $current = $this->contentLayoutRepository->search(new Criteria([$layoutId]), $context)->getEntities()->first();

        if ($current?->getName() === $name) {
            return;
        }

        $this->contentLayoutRepository->update([['id' => $layoutId, 'name' => $name]], $context);
    }

    /**
     * @template T
     *
     * @param \Closure(string): T $operation receives the id of the published revision
     *
     * @return T
     */
    private function locked(string $layoutId, \Closure $operation): mixed
    {
        return $this->connection->transactional(fn (): mixed => $operation($this->reconcile($layoutId)));
    }

    /**
     * The `layout` column is also written outside publishing: persisted mutations, the Sync API, migrations. A
     * column that no longer holds what the last publish wrote becomes the published revision, so a new draft
     * starts from the live state and publishing a draft opened before that write fails instead of reverting it.
     * The revision id is derived from the content, so the column's state is one revision however often it is read.
     */
    private function reconcile(string $layoutId): string
    {
        $publication = $this->store->lockPublication($layoutId) ?? throw ContentSystemException::contentLayoutNotFound($layoutId);

        if ($publication['revisionId'] !== null && $publication['current']) {
            return $publication['revisionId'];
        }

        $revisionId = Uuid::fromStringToHex('content-layout-revision:' . $layoutId . ':' . $publication['checksum']);
        $createdAt = $publication['revisionId'] === null ? $publication['createdAt'] : $this->now();

        $this->store->adoptLayoutColumn($layoutId, $revisionId, $publication['revisionId'], $createdAt);
        $this->store->markPublished($layoutId, $revisionId, $createdAt);

        return $revisionId;
    }

    private function branch(string $layoutId, string $branchId): LayoutBranch
    {
        return $this->store->branch($layoutId, $branchId) ?? throw ContentSystemException::contentLayoutBranchNotFound($layoutId, $branchId);
    }

    /**
     * @return list<StoredElement>
     */
    private function tree(string $layoutId, string $revisionId): array
    {
        return $this->store->tree($layoutId, $revisionId) ?? throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $revisionId);
    }

    private function userId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
