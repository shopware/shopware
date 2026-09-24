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
 * DAL, which runs the full write gate including resolvability. Every write locks the layout row for its
 * transaction, so concurrent saves on one layout serialize and the stale one fails its head check.
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
        $branches = array_values($this->graph($layoutId)->branches);

        usort($branches, static fn (LayoutBranch $a, LayoutBranch $b): int => $b->updatedAt <=> $a->updatedAt);

        return $branches;
    }

    /**
     * @return array{branch: LayoutBranch, head: LayoutRevision}
     */
    public function getBranch(string $layoutId, string $branchId): array
    {
        $graph = $this->graph($layoutId);
        $branch = $this->branch($graph, $layoutId, $branchId);

        return ['branch' => $branch, 'head' => $this->revision($graph, $layoutId, $branch->head)];
    }

    public function listRevisions(string $layoutId): RevisionGraph
    {
        return $this->graph($layoutId);
    }

    public function createBranch(string $layoutId, ?string $name = null, ?string $fromRevisionId = null): LayoutBranch
    {
        return $this->connection->transactional(function () use ($layoutId, $name, $fromRevisionId): LayoutBranch {
            $graph = $this->graph($layoutId, forUpdate: true);
            $base = $this->revision($graph, $layoutId, $fromRevisionId ?? $graph->published)->id;
            $now = $this->now();

            $branch = new LayoutBranch(Uuid::randomHex(), $name ?? self::DEFAULT_BRANCH_NAME, $base, $base, $now, $now);

            $this->store->save($layoutId, $graph->withBranch($branch));

            return $branch;
        });
    }

    /**
     * The layout name is not versioned: a `$name` that differs from the layout's is written to the layout row.
     *
     * @param array<array-key, mixed> $layout
     *
     * @return array{graph: RevisionGraph, revision: LayoutRevision, branch: LayoutBranch}
     */
    public function saveRevision(string $layoutId, string $branchId, array $layout, string $expectedHead, ?string $name, Context $context): array
    {
        return $this->connection->transactional(function () use ($layoutId, $branchId, $layout, $expectedHead, $name, $context): array {
            $graph = $this->graph($layoutId, forUpdate: true);
            $branch = $this->branch($graph, $layoutId, $branchId);

            if ($branch->head !== $expectedHead) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            $now = $this->now();
            $revision = new LayoutRevision(Uuid::randomHex(), $branch->head, $now, $this->userId($context), $this->admit($layout));
            $branch = $branch->withHead($revision->id, $now);
            $graph = $graph->withRevision($revision)->withBranch($branch);

            $this->store->save($layoutId, $graph);

            if ($name !== null) {
                $this->rename($layoutId, $name, $context);
            }

            return ['graph' => $graph, 'revision' => $revision, 'branch' => $branch];
        });
    }

    /**
     * The DAL update and the graph save share one transaction, so a tree the write gate refuses leaves the
     * published pointer where it was.
     */
    public function publish(string $layoutId, string $revisionId, ?string $deleteBranchId, Context $context): void
    {
        $this->connection->transactional(function () use ($layoutId, $revisionId, $deleteBranchId, $context): void {
            $graph = $this->graph($layoutId, forUpdate: true);
            $revision = $this->revision($graph, $layoutId, $revisionId);

            if ($deleteBranchId !== null) {
                $this->branch($graph, $layoutId, $deleteBranchId);
                $graph = $graph->withoutBranch($deleteBranchId);
            }

            $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($layoutId, $revision): void {
                $this->contentLayoutRepository->update([['id' => $layoutId, 'layout' => $revision->tree]], $systemContext);
            });

            $this->store->save($layoutId, $graph->withPublished($revisionId));
        });
    }

    public function deleteBranch(string $layoutId, string $branchId): void
    {
        $this->connection->transactional(function () use ($layoutId, $branchId): void {
            $graph = $this->graph($layoutId, forUpdate: true);
            $this->branch($graph, $layoutId, $branchId);

            $this->store->save($layoutId, $graph->withoutBranch($branchId)->reachableOnly());
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

    private function graph(string $layoutId, bool $forUpdate = false): RevisionGraph
    {
        return $this->store->load($layoutId, $forUpdate) ?? throw ContentSystemException::contentLayoutNotFound($layoutId);
    }

    private function branch(RevisionGraph $graph, string $layoutId, string $branchId): LayoutBranch
    {
        return $graph->branch($branchId) ?? throw ContentSystemException::contentLayoutBranchNotFound($layoutId, $branchId);
    }

    private function revision(RevisionGraph $graph, string $layoutId, string $revisionId): LayoutRevision
    {
        return $graph->revision($revisionId) ?? throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $revisionId);
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
