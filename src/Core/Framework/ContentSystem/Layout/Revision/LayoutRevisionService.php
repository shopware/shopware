<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Revision;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeConstraints;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutGate;
use Shopware\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Drafts of a content layout are named pointers into its revision graph. Every persisted edit appends a revision
 * to a draft; publishing writes the draft head's tree to the `layout` column through the DAL, which runs the full
 * write gate including resolvability, and moves the live pointer onto it. Every write runs under the
 * {@see LayoutRowLock} that persisted mutations take too, and first reconciles the live pointer with the `layout`
 * column, see {@see reconcile()}; concurrent writers of one layout therefore serialize and the stale one fails its
 * head or version check. Reading drafts and the published history take neither, since reconciling never changes
 * a draft or a stored tree, so a reader never waits for a writer. Editing, publishing and discarding compact the
 * history, see {@see RevisionGraph::compaction()}: a draft keeps only its head, and the published history its
 * latest entries.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
readonly class LayoutRevisionService
{
    private const DEFAULT_DRAFT_NAME = 'Draft';

    private const PUBLISHED_REVISIONS_KEPT = 20;

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private LayoutRowLock $rowLock,
        private LayoutRevisionStore $store,
        private LayoutWriteBoundary $writeBoundary,
        private StoredTreeCodec $treeCodec,
        private StoredTreeConstraints $treeConstraints,
        private ValidatorInterface $validator,
        private LayoutGate $gate,
        private ViolationConstraintMapper $violationMapper,
        private EntityRepository $contentLayoutRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<LayoutDraft>
     */
    public function listDrafts(string $layoutId): array
    {
        if (!$this->store->layoutExists($layoutId)) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        return $this->store->drafts($layoutId);
    }

    /**
     * @return array{draft: LayoutDraft, layout: list<StoredElement>}
     */
    public function getDraft(string $layoutId, string $draftId): array
    {
        $draft = $this->store->draftWithHead($layoutId, $draftId);

        if ($draft !== null) {
            return $draft;
        }

        if (!$this->store->layoutExists($layoutId)) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        throw ContentSystemException::contentLayoutDraftNotFound($layoutId, $draftId);
    }

    /**
     * The revisions a draft can be opened from to roll back, see {@see LayoutRevisionStore::publishedRevisions()}.
     * A layout write that bypassed publishing appears only after the next write through this service adopts it.
     *
     * @return list<array{revision: LayoutRevision, live: bool}>
     */
    public function publishedRevisions(string $layoutId): array
    {
        if (!$this->store->layoutExists($layoutId)) {
            throw ContentSystemException::contentLayoutNotFound($layoutId);
        }

        return $this->store->publishedRevisions($layoutId);
    }

    public function createDraft(string $layoutId, Context $context, ?string $name = null, ?string $fromRevisionId = null): LayoutDraft
    {
        return $this->locked($layoutId, function (string $live) use ($layoutId, $context, $name, $fromRevisionId): LayoutDraft {
            $base = $fromRevisionId ?? $live;

            if ($this->store->graph($layoutId, $live)->revision($base) === null) {
                throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $base);
            }

            $now = $this->now();
            $draft = new LayoutDraft(Uuid::randomHex(), $name ?? self::DEFAULT_DRAFT_NAME, $base, $base, $live, $now, $this->userId($context), $now);

            $this->store->saveDraft($layoutId, $draft);

            return $draft;
        });
    }

    /**
     * Appends the tree `$derive` makes from the draft head, admitted like a layout write without its resolvability
     * step. The head is checked before `$derive` runs, so a derivation never works on a head its caller has not seen.
     *
     * @param \Closure(list<StoredElement>): StoredTree $derive
     *
     * @return array{revision: LayoutRevision, draft: LayoutDraft, layout: list<StoredElement>}
     */
    public function appendRevision(string $layoutId, string $draftId, string $expectedHeadRevisionId, \Closure $derive, Context $context): array
    {
        return $this->locked($layoutId, function (string $live) use ($layoutId, $draftId, $expectedHeadRevisionId, $derive, $context): array {
            $appended = $this->append($layoutId, $draftId, $expectedHeadRevisionId, fn (array $head): array => $this->admit($derive($head)), $context);
            $this->compact($layoutId, $live);

            return $appended;
        });
    }

    /**
     * Publishes the head the caller last saw and removes the draft. Publishing never replaces a live state its
     * caller has not seen: if the live revision changed since the draft was opened, it may only have moved forward,
     * and the draft must contain it. Containment alone is not enough, since a rollback moves live back to a revision
     * every newer draft contains. The DAL update and the pointer move share one transaction, so a tree the write gate refuses
     * leaves the draft and the live pointer where they were.
     */
    public function publish(string $layoutId, string $draftId, string $expectedHeadRevisionId, Context $context): void
    {
        $this->locked($layoutId, function (string $live) use ($layoutId, $draftId, $expectedHeadRevisionId, $context): void {
            $draft = $this->draft($layoutId, $draftId);

            if ($draft->head !== $expectedHeadRevisionId) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            if ($draft->liveAtOpen !== $live && !$this->movedForwardInto($layoutId, $live, $draft)) {
                throw ContentSystemException::layoutPublishConflict($layoutId, $live);
            }

            $this->store->deleteDraft($layoutId, $draftId);
            $this->contentLayoutRepository->update([['id' => $layoutId, 'layout' => $this->tree($layoutId, $draft->head)]], $context);
            $published = $this->writtenRevision($layoutId, $draft->head, $context);
            $this->store->markPublished($layoutId, $published, $this->now(), $this->userId($context));
            $this->compact($layoutId, $published);
        });
    }

    public function discardDraft(string $layoutId, string $draftId): void
    {
        $this->locked($layoutId, function (string $live) use ($layoutId, $draftId): void {
            $this->draft($layoutId, $draftId);
            $this->store->deleteDraft($layoutId, $draftId);
            $this->compact($layoutId, $live);
        });
    }

    /**
     * The DAL write runs the write boundary again, and its output depends on the registries at publish time: a type
     * default added since the draft was edited lands in the column but not in the head. Marking the head live
     * would leave the column looking like a write that bypassed publishing, which the next write adopts and every
     * other open draft then conflicts with; the column becomes a revision on top of the head instead.
     */
    private function writtenRevision(string $layoutId, string $head, Context $context): string
    {
        $checksum = $this->store->divergentLayoutChecksum($layoutId, $head);

        if ($checksum === null) {
            return $head;
        }

        $revisionId = $this->columnRevisionId($layoutId, $checksum);
        $this->store->adoptLayoutColumn($layoutId, $revisionId, $head, $this->now(), $this->userId($context));

        return $revisionId;
    }

    private function columnRevisionId(string $layoutId, string $checksum): string
    {
        return Uuid::fromStringToHex('content-layout-revision:' . $layoutId . ':' . $checksum);
    }

    private function compact(string $layoutId, string $live): void
    {
        $compaction = $this->store->graph($layoutId, $live)->compaction(self::PUBLISHED_REVISIONS_KEPT);

        $this->store->reparent($layoutId, $compaction['reparent']);
        $this->store->deleteRevisions($layoutId, $compaction['delete']);
    }

    private function movedForwardInto(string $layoutId, string $live, LayoutDraft $draft): bool
    {
        $graph = $this->store->graph($layoutId, $live);

        return $graph->isAncestorOrSelf($draft->liveAtOpen, $live) && $graph->isAncestorOrSelf($live, $draft->head);
    }

    /**
     * @param \Closure(list<StoredElement>): list<StoredElement> $admitted
     *
     * @return array{revision: LayoutRevision, draft: LayoutDraft, layout: list<StoredElement>}
     */
    private function append(string $layoutId, string $draftId, string $expectedHeadRevisionId, \Closure $admitted, Context $context): array
    {
        $draft = $this->draft($layoutId, $draftId);

        if ($draft->head !== $expectedHeadRevisionId) {
            throw ContentSystemException::layoutVersionConflict($layoutId);
        }

        $tree = $admitted($this->tree($layoutId, $draft->head));

        $now = $this->now();
        $revision = new LayoutRevision(Uuid::randomHex(), $draft->head, $now, $this->userId($context));
        $draft = $draft->withHead($revision->id, $now);

        $this->store->insertRevision($layoutId, $revision, $tree);
        $this->store->saveDraft($layoutId, $draft);

        return ['revision' => $revision, 'draft' => $draft, 'layout' => $tree];
    }

    /**
     * The layout write's well-formedness admission without its resolvability step, which only publishing
     * enforces: the write boundary, the stored-tree constraints plus the `NotBlank` the required layout field
     * adds, and the gate's intrinsic errors.
     *
     * @return list<StoredElement>
     */
    private function admit(StoredTree $tree): array
    {
        try {
            $tree = $this->writeBoundary->apply($tree);
        } catch (ContentSystemException $exception) {
            if (!ContentSystemException::isClientDefect($exception)) {
                throw $exception;
            }

            throw ContentSystemException::layoutWriteRejection($exception, 'layout', $this->treeCodec->encode($tree), '');
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

    /**
     * @template T
     *
     * @param \Closure(string): T $operation receives the id of the live revision
     *
     * @return T
     */
    private function locked(string $layoutId, \Closure $operation): mixed
    {
        return $this->rowLock->run($layoutId, fn (): mixed => $operation($this->reconcile($layoutId)));
    }

    /**
     * The `layout` column is also written outside publishing: live persisted mutations, the Sync API, migrations. A
     * column that no longer holds the live revision's tree becomes the live revision, so a new draft
     * starts from the live state and publishing a draft opened before that write fails instead of reverting it.
     * The revision id is derived from the content, so the column's state is one revision however often it is read.
     */
    private function reconcile(string $layoutId): string
    {
        $publication = $this->store->publication($layoutId) ?? throw ContentSystemException::contentLayoutNotFound($layoutId);

        if ($publication['revisionId'] !== null && $publication['current']) {
            return $publication['revisionId'];
        }

        $revisionId = $this->columnRevisionId($layoutId, $publication['checksum']);
        $createdAt = $publication['revisionId'] === null ? $publication['createdAt'] : $this->now();

        $this->store->adoptLayoutColumn($layoutId, $revisionId, $publication['revisionId'], $createdAt, null);
        $this->store->markPublished($layoutId, $revisionId, $createdAt, null);

        return $revisionId;
    }

    private function draft(string $layoutId, string $draftId): LayoutDraft
    {
        return $this->store->draft($layoutId, $draftId) ?? throw ContentSystemException::contentLayoutDraftNotFound($layoutId, $draftId);
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
