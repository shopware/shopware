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
 * write gate including resolvability, and moves the live pointer onto it. Every operation, reads included, runs
 * under the {@see LayoutRowLock} that persisted mutations take too, and first reconciles the live pointer with the
 * `layout` column, see {@see reconcile()}; concurrent writers of one layout therefore serialize and the stale one
 * fails its head or version check.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LayoutRevisionService
{
    private const DEFAULT_DRAFT_NAME = 'Draft';

    /**
     * @param EntityRepository<ContentLayoutCollection> $contentLayoutRepository
     */
    public function __construct(
        private readonly LayoutRowLock $rowLock,
        private readonly LayoutRevisionStore $store,
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
     * @return list<LayoutDraft>
     */
    public function listDrafts(string $layoutId): array
    {
        return $this->locked($layoutId, function (string $published) use ($layoutId): array {
            $drafts = array_values($this->store->graph($layoutId, $published)->drafts);

            usort($drafts, static fn (LayoutDraft $a, LayoutDraft $b): int => $b->updatedAt <=> $a->updatedAt);

            return $drafts;
        });
    }

    /**
     * @return array{draft: LayoutDraft, layout: list<StoredElement>}
     */
    public function getDraft(string $layoutId, string $draftId): array
    {
        return $this->locked($layoutId, function () use ($layoutId, $draftId): array {
            $draft = $this->draft($layoutId, $draftId);

            return ['draft' => $draft, 'layout' => $this->tree($layoutId, $draft->head)];
        });
    }

    public function history(string $layoutId): RevisionGraph
    {
        return $this->locked($layoutId, fn (string $published): RevisionGraph => $this->store->graph($layoutId, $published));
    }

    public function createDraft(string $layoutId, ?string $name = null, ?string $fromRevisionId = null): LayoutDraft
    {
        return $this->locked($layoutId, function (string $published) use ($layoutId, $name, $fromRevisionId): LayoutDraft {
            $base = $fromRevisionId ?? $published;

            if ($this->store->graph($layoutId, $published)->revision($base) === null) {
                throw ContentSystemException::contentLayoutRevisionNotFound($layoutId, $base);
            }

            $now = $this->now();
            $draft = new LayoutDraft(Uuid::randomHex(), $name ?? self::DEFAULT_DRAFT_NAME, $base, $base, $published, $now, $now);

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
    public function appendRevision(string $layoutId, string $draftId, string $expectedHead, \Closure $derive, Context $context): array
    {
        return $this->locked($layoutId, fn (): array => $this->append(
            $layoutId,
            $draftId,
            $expectedHead,
            fn (array $head): array => $this->admit($derive($head)),
            $context,
        ));
    }

    /**
     * Publishes the head the caller last saw and removes the draft. Publishing never replaces a live state its
     * caller has not seen: the draft must still be based on the live state it was opened from, or contain the
     * current one. The DAL update and the pointer move share one transaction, so a tree the write gate refuses
     * leaves the draft and the live pointer where they were.
     */
    public function publish(string $layoutId, string $draftId, string $expectedHead, Context $context): void
    {
        $this->locked($layoutId, function (string $published) use ($layoutId, $draftId, $expectedHead, $context): void {
            $draft = $this->draft($layoutId, $draftId);

            if ($draft->head !== $expectedHead) {
                throw ContentSystemException::layoutVersionConflict($layoutId);
            }

            if ($draft->live !== $published && !$this->store->graph($layoutId, $published)->isAncestorOrSelf($published, $draft->head)) {
                throw ContentSystemException::layoutPublishConflict($layoutId, $published);
            }

            $this->store->deleteDraft($layoutId, $draftId);
            $this->contentLayoutRepository->update([['id' => $layoutId, 'layout' => $this->tree($layoutId, $draft->head)]], $context);
            $this->store->markPublished($layoutId, $draft->head, $this->now());
        });
    }

    public function deleteDraft(string $layoutId, string $draftId): void
    {
        $this->locked($layoutId, function (string $published) use ($layoutId, $draftId): void {
            $this->draft($layoutId, $draftId);
            $this->store->deleteDraft($layoutId, $draftId);

            $this->store->deleteRevisions($layoutId, $this->store->graph($layoutId, $published)->unreachableIds());
        });
    }

    /**
     * @param \Closure(list<StoredElement>): list<StoredElement> $admitted
     *
     * @return array{revision: LayoutRevision, draft: LayoutDraft, layout: list<StoredElement>}
     */
    private function append(string $layoutId, string $draftId, string $expectedHead, \Closure $admitted, Context $context): array
    {
        $draft = $this->draft($layoutId, $draftId);

        if ($draft->head !== $expectedHead) {
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
     * @param \Closure(string): T $operation receives the id of the published revision
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

        $revisionId = Uuid::fromStringToHex('content-layout-revision:' . $layoutId . ':' . $publication['checksum']);
        $createdAt = $publication['revisionId'] === null ? $publication['createdAt'] : $this->now();

        $this->store->adoptLayoutColumn($layoutId, $revisionId, $publication['revisionId'], $createdAt);
        $this->store->markPublished($layoutId, $revisionId, $createdAt);

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
