<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStore;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\RevisionGraph;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class LayoutRevisionServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private LayoutRevisionService $service;

    private Context $context;

    protected function setUp(): void
    {
        $this->service = static::getContainer()->get(LayoutRevisionService::class);
        $this->context = Context::createDefaultContext();
    }

    #[TestDox('a draft records the admin user who created it, and a revision the one who first published it')]
    public function testDraftAndPublicationRecordTheAdminUser(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $creator = Uuid::randomHex();
        $publisher = Uuid::randomHex();

        $draft = $this->service->createDraft($layoutId, $this->asUser($creator));
        static::assertSame($creator, $draft->createdBy);
        static::assertSame($creator, $this->service->listDrafts($layoutId)[0]->createdBy);

        $saved = $this->save($layoutId, $draft->id, ['draft'], $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->asUser($publisher));

        $restore = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->asUser(Uuid::randomHex()));

        $history = $this->graph($layoutId);
        static::assertSame($publisher, $history->revision($saved['revision']->id)?->publishedBy);
        static::assertNull($history->revision($initial)?->publishedBy);
    }

    #[TestDox('the first write adopts the layout column as the single published revision, however often it is written')]
    public function testFirstWriteAdoptsTheLayoutColumn(): void
    {
        $layoutId = $this->createLayout(['live-a']);

        $first = $this->adoptLiveRevision($layoutId);
        $second = $this->adoptLiveRevision($layoutId);

        $history = $this->graph($layoutId);
        static::assertSame($first, $second);
        static::assertCount(1, $history->revisions);
        static::assertNotNull($history->revision($first)?->publishedAt);
    }

    #[TestDox('publishing a draft writes its head to the layout column, moves the live pointer and removes the draft')]
    public function testPublishingADraft(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, ['draft-a', 'draft-b'], $draft->head);

        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);

        $history = $this->graph($layoutId);
        static::assertSame(['draft-a', 'draft-b'], $this->liveIds($layoutId));
        static::assertSame($saved['revision']->id, $history->live);
        static::assertCount(2, $history->revisions);
        static::assertSame([], $this->service->listDrafts($layoutId));
    }

    #[TestDox('publishing a head the caller has not seen fails without writing and keeps the draft')]
    public function testPublishingAStaleHeadConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->save($layoutId, $draft->id, ['draft'], $draft->head);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_VERSION_CONFLICT,
            fn () => $this->service->publish($layoutId, $draft->id, $draft->head, $this->context),
        );
        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertCount(1, $this->service->listDrafts($layoutId));
    }

    #[TestDox('publishing a draft fails without writing once another draft went live after it was opened')]
    public function testPublishingAStaleDraftConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $first = $this->service->createDraft($layoutId, $this->context);
        $second = $this->service->createDraft($layoutId, $this->context);
        $firstSaved = $this->save($layoutId, $first->id, ['first'], $first->head);
        $secondSaved = $this->save($layoutId, $second->id, ['second'], $second->head);

        $this->service->publish($layoutId, $second->id, $secondSaved['draft']->head, $this->context);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $first->id, $firstSaved['draft']->head, $this->context),
        );
        static::assertSame(['second'], $this->liveIds($layoutId));
        static::assertCount(1, $this->service->listDrafts($layoutId));
    }

    #[TestDox('a layout write that bypasses publishing becomes the live revision, so a draft opened before it cannot revert it')]
    public function testDirectLayoutWriteIsAdopted(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, ['draft'], $draft->head);

        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);

        $adopted = $this->service->createDraft($layoutId, $this->context)->base;
        static::assertNotSame($initial, $adopted);
        static::assertSame($initial, $this->graph($layoutId)->revision($adopted)?->parent);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context),
        );
        static::assertSame(['direct'], $this->liveIds($layoutId));
        static::assertSame($adopted, $this->graph($layoutId)->live);
    }

    #[TestDox('a head the write gate changes on publishing goes live as the column holds it, so the next write adopts nothing')]
    public function testPublishingAHeadTheWriteGateChangesPublishesTheColumn(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $this->writeColumnPastTheWriteGate($layoutId, [new StoredElement('unseeded', TestElementTypeLoader::DEFAULTED_PRIMITIVE)]);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $publisher = Uuid::randomHex();

        $this->service->publish($layoutId, $draft->id, $draft->head, $this->asUser($publisher));

        $history = $this->graph($layoutId);
        $live = $history->revision($history->live);
        static::assertNotNull($live);
        static::assertNotSame($draft->head, $live->id);
        static::assertSame($draft->head, $live->parent);
        static::assertSame($publisher, $live->publishedBy);
        static::assertSame($live->id, $this->service->createDraft($layoutId, $this->context)->base);
    }

    #[TestDox('a draft opened from an older revision rolls the layout back and the live pointer follows it')]
    public function testDraftOpenedFromHistoryCanBePublished(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, ['newer'], $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);

        $restore = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->context);

        $history = $this->graph($layoutId);
        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($initial, $history->live);
        static::assertCount(2, $history->revisions);
    }

    #[TestDox('publishing a draft opened before a rollback fails without writing, although the draft descends from the restored revision')]
    public function testPublishingADraftOpenedBeforeARollbackConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $edit = $this->service->createDraft($layoutId, $this->context);
        $editSaved = $this->save($layoutId, $edit->id, ['newer'], $edit->head);
        $this->service->publish($layoutId, $edit->id, $editSaved['draft']->head, $this->context);

        $stale = $this->service->createDraft($layoutId, $this->context);
        $staleSaved = $this->save($layoutId, $stale->id, ['newer', 'stale'], $stale->head);

        $restore = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->context);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $stale->id, $staleSaved['draft']->head, $this->context),
        );
        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($initial, $this->graph($layoutId)->live);
        static::assertCount(1, $this->service->listDrafts($layoutId));
    }

    #[TestDox('a draft branched from another draft can be published after it, since live only moved forward into the draft')]
    public function testDraftBranchedFromAnotherDraftCanBePublishedAfterIt(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $first = $this->service->createDraft($layoutId, $this->context);
        $firstSaved = $this->save($layoutId, $first->id, ['first'], $first->head);

        $branched = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $firstSaved['draft']->head);
        $branchedSaved = $this->save($layoutId, $branched->id, ['first', 'second'], $branched->head);

        $this->service->publish($layoutId, $first->id, $firstSaved['draft']->head, $this->context);
        $this->service->publish($layoutId, $branched->id, $branchedSaved['draft']->head, $this->context);

        static::assertSame(['first', 'second'], $this->liveIds($layoutId));
        static::assertSame($branchedSaved['revision']->id, $this->graph($layoutId)->live);
    }

    #[TestDox('a draft branched from another draft\'s head conflicts after that draft when the write gate changed the head on publishing it, since live then moved onto a revision the branch does not contain')]
    public function testDraftBranchedFromAHeadTheWriteGateChangesConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $first = $this->service->createDraft($layoutId, $this->context);
        $firstHead = $this->save($layoutId, $first->id, ['first'], $first->head)['draft']->head;
        $this->writeRevisionPastTheWriteGate($firstHead, [new StoredElement('unseeded', TestElementTypeLoader::DEFAULTED_PRIMITIVE)]);

        $branched = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $firstHead);
        $branchedHead = $this->save($layoutId, $branched->id, ['first', 'second'], $branched->head)['draft']->head;

        $this->service->publish($layoutId, $first->id, $firstHead, $this->context);
        static::assertSame($firstHead, $this->graph($layoutId)->revision($this->graph($layoutId)->live)?->parent);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $branched->id, $branchedHead, $this->context),
        );
        static::assertSame(['unseeded'], $this->liveIds($layoutId));
        static::assertSame($branchedHead, $this->service->getDraft($layoutId, $branched->id)['draft']->head);
    }

    #[TestDox('discarding a draft deletes its head and keeps the published revisions')]
    public function testDiscardingADraftDeletesItsRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $published = $this->service->createDraft($layoutId, $this->context);
        $publishedSaved = $this->save($layoutId, $published->id, ['published'], $published->head);
        $this->service->publish($layoutId, $published->id, $publishedSaved['draft']->head, $this->context);

        $draft = $this->service->createDraft($layoutId, $this->context);
        $first = $this->save($layoutId, $draft->id, ['one'], $draft->head);
        $this->save($layoutId, $draft->id, ['two'], $first['draft']->head);

        static::assertCount(3, $this->graph($layoutId)->revisions);

        $this->service->discardDraft($layoutId, $draft->id);

        $history = $this->graph($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($publishedSaved['revision']->id, $history->live);
    }

    #[TestDox('a draft keeps only its head, attached to the revision it started from')]
    public function testADraftKeepsOnlyItsHead(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $head = $draft->head;
        foreach (['one', 'two', 'three'] as $elementId) {
            $head = $this->save($layoutId, $draft->id, [$elementId], $head)['draft']->head;
        }

        $history = $this->graph($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($draft->base, $history->revision($head)?->parent);
    }

    #[TestDox('publishing keeps the 20 most recently live revisions, so an older one can no longer be restored')]
    public function testPublishingKeepsTheLatestPublishedRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        for ($i = 1; $i <= 20; ++$i) {
            $this->publishEdit($layoutId, ['edit-' . $i]);
        }

        $history = $this->graph($layoutId);
        static::assertCount(20, $history->revisions);
        static::assertNull($history->revision($initial));
        $this->assertFailsWith(
            ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND,
            fn () => $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial),
        );
    }

    #[TestDox('a revision restored by a rollback counts as recently live, so the publish after the rollback can be undone too')]
    public function testARollbackRenewsTheRestoredRevision(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        for ($i = 1; $i <= 19; ++$i) {
            $this->publishEdit($layoutId, ['edit-' . $i]);
        }
        $rollback = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $rollback->id, $rollback->head, $this->context);

        $this->publishEdit($layoutId, ['after-rollback']);

        $restore = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->context);
        static::assertSame(['live-a'], $this->liveIds($layoutId));
    }

    #[TestDox('publishing a draft the write gate refuses keeps the draft, the live pointer and the layout column')]
    public function testPublishingARefusedDraftKeepsTheDraft(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->service->appendRevision(
            $layoutId,
            $draft->id,
            $draft->head,
            static fn (): StoredTree => new StoredTree([new StoredElement('unresolvable', TestElementTypeLoader::UNRESOLVABLE)]),
            $this->context,
        );

        try {
            $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);
            static::fail('Expected the write gate to refuse the unresolvable tree.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Required property "target" is not deterministically resolvable', $exception->getMessage());
        }

        static::assertSame($saved['draft']->head, $this->service->getDraft($layoutId, $draft->id)['draft']->head);
        static::assertSame($initial, $this->graph($layoutId)->live);
        static::assertSame(['live-a'], $this->liveIds($layoutId));
    }

    #[TestDox('the published revisions are the rollback targets, most recently live first, read without adopting a direct write')]
    public function testPublishedRevisionsListTheRollbackTargets(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, ['newer'], $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);
        $rollback = $this->service->createDraft($layoutId, $this->context, fromRevisionId: $initial);
        $this->service->publish($layoutId, $rollback->id, $rollback->head, $this->context);
        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);

        $published = $this->service->publishedRevisions($layoutId);

        static::assertSame([$initial, $saved['revision']->id], array_map(static fn (array $entry): string => $entry['revision']->id, $published));
        static::assertSame([true, false], array_column($published, 'live'));
        static::assertGreaterThan($published[0]['revision']->publishedAt, $published[0]['revision']->lastLiveAt);
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_NOT_FOUND, fn () => $this->service->publishedRevisions(Uuid::randomHex()));
    }

    #[TestDox('an open draft keeps the live revision it was opened from beyond the published window, so publishing it still conflicts')]
    public function testAnOpenDraftKeepsItsLiveAtOpenRevision(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->adoptLiveRevision($layoutId);
        $stale = $this->service->createDraft($layoutId, $this->context);
        $staleSaved = $this->save($layoutId, $stale->id, ['stale'], $stale->head);
        for ($i = 1; $i <= 20; ++$i) {
            $this->publishEdit($layoutId, ['edit-' . $i]);
        }

        static::assertNotNull($this->graph($layoutId)->revision($initial));
        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $stale->id, $staleSaved['draft']->head, $this->context),
        );
    }

    #[TestDox('a draft branches from another draft\'s current head only, since its earlier heads are deleted')]
    public function testBranchingFromASupersededHeadIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $first = $this->save($layoutId, $draft->id, ['one'], $draft->head);
        $this->save($layoutId, $draft->id, ['two'], $first['draft']->head);

        $this->assertFailsWith(
            ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND,
            fn () => $this->service->createDraft($layoutId, $this->context, fromRevisionId: $first['draft']->head),
        );
    }

    #[TestDox('listing and reading drafts never write: a layout write past publishing is adopted only by the next write')]
    public function testReadingDraftsDoesNotReconcile(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);
        $rowsBefore = $this->revisionRows($layoutId);

        static::assertSame([$draft->id], array_map(static fn (LayoutDraft $listed): string => $listed->id, $this->service->listDrafts($layoutId)));
        $read = $this->service->getDraft($layoutId, $draft->id);

        static::assertSame($draft->head, $read['draft']->head);
        static::assertSame(['live-a'], array_map(static fn (StoredElement $element): string => $element->id, $read['layout']));
        static::assertSame($rowsBefore, $this->revisionRows($layoutId));

        $this->service->createDraft($layoutId, $this->context);
        static::assertSame($rowsBefore + 1, $this->revisionRows($layoutId));
    }

    #[TestDox('listing or reading drafts of a layout that does not exist is a layout not found, not an empty answer')]
    public function testReadingDraftsOfAnUnknownLayoutIsNotFound(): void
    {
        $layoutId = Uuid::randomHex();

        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_NOT_FOUND, fn () => $this->service->listDrafts($layoutId));
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_NOT_FOUND, fn () => $this->service->getDraft($layoutId, Uuid::randomHex()));
    }

    #[TestDox('reading a draft the layout does not have is a draft not found')]
    public function testReadingAnUnknownDraftIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);

        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->getDraft($layoutId, Uuid::randomHex()));
    }

    #[TestDox('a draft of another layout is not found through this layout, whether read, edited, published or discarded')]
    public function testAnotherLayoutsDraftIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $otherLayoutId = $this->createLayout(['live-b']);
        $otherDraft = $this->service->createDraft($otherLayoutId, $this->context);
        $otherHead = $this->save($otherLayoutId, $otherDraft->id, ['edited-b'], $otherDraft->head)['draft']->head;

        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->getDraft($layoutId, $otherDraft->id));
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->save($layoutId, $otherDraft->id, ['taken-over'], $otherHead));
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->publish($layoutId, $otherDraft->id, $otherHead, $this->context));
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->discardDraft($layoutId, $otherDraft->id));

        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($otherHead, $this->service->getDraft($otherLayoutId, $otherDraft->id)['draft']->head);
        static::assertSame(['live-b'], $this->liveIds($otherLayoutId));
    }

    #[TestDox('a draft cannot be opened from a revision of another layout')]
    public function testDraftFromAnotherLayoutsRevisionIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $otherLayoutId = $this->createLayout(['live-b']);
        $otherDraft = $this->service->createDraft($otherLayoutId, $this->context);
        $otherRevision = $this->save($otherLayoutId, $otherDraft->id, ['edited-b'], $otherDraft->head)['revision']->id;

        $this->assertFailsWith(
            ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND,
            fn () => $this->service->createDraft($layoutId, $this->context, fromRevisionId: $otherRevision),
        );

        static::assertSame([], $this->service->listDrafts($layoutId));
    }

    #[TestDox('discarding a draft the layout does not have, or no longer has after publishing it, is a draft not found')]
    public function testDiscardingAnUnknownOrPublishedDraftIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->service->publish($layoutId, $draft->id, $draft->head, $this->context);

        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->discardDraft($layoutId, Uuid::randomHex()));
        $this->assertFailsWith(ContentSystemException::CONTENT_LAYOUT_DRAFT_NOT_FOUND, fn () => $this->service->discardDraft($layoutId, $draft->id));
    }

    #[TestDox('the revision rows of a layout are removed with the layout')]
    public function testRevisionsAreDeletedWithTheLayout(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->save($layoutId, $draft->id, ['draft'], $draft->head);

        $this->repository()->delete([['id' => $layoutId]], $this->context);

        $connection = static::getContainer()->get(Connection::class);
        foreach (['content_layout_revision', 'content_layout_draft'] as $table) {
            static::assertSame(0, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `' . $table . '` WHERE `content_layout_id` = :id',
                ['id' => Uuid::fromHexToBytes($layoutId)],
            ), $table);
        }
    }

    #[TestDox('appending a revision derives the new tree from the draft head and moves the head onto it')]
    public function testAppendRevisionDerivesFromTheHead(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, ['draft-a'], $draft->head);
        $seen = null;

        $appended = $this->service->appendRevision(
            $layoutId,
            $draft->id,
            $saved['draft']->head,
            function (array $head) use (&$seen): StoredTree {
                $seen = array_map(static fn (StoredElement $element): string => $element->id, $head);

                return new StoredTree($this->storedTree(['draft-a', 'draft-b']));
            },
            $this->context,
        );

        static::assertSame(['draft-a'], $seen);
        static::assertSame($saved['draft']->head, $appended['revision']->parent);
        static::assertSame($appended['revision']->id, $appended['draft']->head);
        static::assertSame(['draft-a', 'draft-b'], array_map(static fn (StoredElement $element): string => $element->id, $appended['layout']));
        static::assertSame(['live-a'], $this->liveIds($layoutId));
    }

    #[TestDox('a stale head is rejected before the new tree is derived')]
    public function testAppendRevisionWithStaleHeadNeverDerives(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->save($layoutId, $draft->id, ['draft-a'], $draft->head);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_VERSION_CONFLICT,
            fn () => $this->service->appendRevision(
                $layoutId,
                $draft->id,
                $draft->head,
                static fn (): StoredTree => static::fail('A stale head must not reach the derivation.'),
                $this->context,
            ),
        );
    }

    #[TestDox('a derived tree that is not well-formed is rejected and leaves the head where it was')]
    public function testAppendRevisionAdmitsTheDerivedTree(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId, $this->context);

        try {
            $this->service->appendRevision(
                $layoutId,
                $draft->id,
                $draft->head,
                fn (): StoredTree => new StoredTree($this->storedTree(['twin', 'twin'])),
                $this->context,
            );
            static::fail('Expected the duplicate element id to be rejected.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }

        static::assertSame($draft->head, $this->service->getDraft($layoutId, $draft->id)['draft']->head);
    }

    /**
     * @param list<string> $elementIds
     *
     * @return array{revision: LayoutRevision, draft: LayoutDraft, layout: list<StoredElement>}
     */
    private function save(string $layoutId, string $draftId, array $elementIds, string $expectedHead): array
    {
        return $this->service->appendRevision(
            $layoutId,
            $draftId,
            $expectedHead,
            fn (): StoredTree => new StoredTree($this->storedTree($elementIds)),
            $this->context,
        );
    }

    /**
     * Opens and discards a draft: the first write to a layout adopts its column as the live revision.
     */
    private function adoptLiveRevision(string $layoutId): string
    {
        $draft = $this->service->createDraft($layoutId, $this->context);
        $this->service->discardDraft($layoutId, $draft->id);

        return $draft->base;
    }

    /**
     * Writes the column the way a migration or the Sync API with raw SQL could, without the write gate seeding
     * type defaults into it.
     *
     * @param list<StoredElement> $tree
     */
    private function writeColumnPastTheWriteGate(string $layoutId, array $tree): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `content_layout` SET `layout` = :layout WHERE `id` = :id',
            [
                'layout' => Json::encode(static::getContainer()->get(StoredTreeCodec::class)->encode(new StoredTree($tree))),
                'id' => Uuid::fromHexToBytes($layoutId),
            ],
        );
    }

    /**
     * Stands in for a revision written before its element types gained a default: the write gate seeds that
     * default when the revision is published.
     *
     * @param list<StoredElement> $tree
     */
    private function writeRevisionPastTheWriteGate(string $revisionId, array $tree): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `content_layout_revision` SET `layout` = :layout WHERE `id` = :id',
            [
                'layout' => Json::encode(static::getContainer()->get(StoredTreeCodec::class)->encode(new StoredTree($tree))),
                'id' => Uuid::fromHexToBytes($revisionId),
            ],
        );
    }

    private function graph(string $layoutId): RevisionGraph
    {
        $store = static::getContainer()->get(LayoutRevisionStore::class);
        $live = $store->publication($layoutId)['revisionId'] ?? null;
        static::assertNotNull($live);

        return $store->graph($layoutId, $live);
    }

    /**
     * @param list<string> $elementIds
     */
    private function publishEdit(string $layoutId, array $elementIds): void
    {
        $draft = $this->service->createDraft($layoutId, $this->context);
        $saved = $this->save($layoutId, $draft->id, $elementIds, $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);
    }

    private function asUser(string $userId): Context
    {
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    private function revisionRows(string $layoutId): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM `content_layout_revision` WHERE `content_layout_id` = :id',
            ['id' => Uuid::fromHexToBytes($layoutId)],
        );
    }

    /**
     * @param \Closure(): mixed $operation
     */
    private function assertFailsWith(string $errorCode, \Closure $operation): void
    {
        try {
            $operation();
        } catch (ContentSystemException $exception) {
            static::assertSame($errorCode, $exception->getErrorCode());

            return;
        }

        static::fail('Expected a ' . $errorCode . ' failure.');
    }

    /**
     * @param list<string> $elementIds
     */
    private function createLayout(array $elementIds): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => 'revision-' . $id,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => $this->tree($elementIds),
        ]], $this->context);

        return $id;
    }

    /**
     * @param list<string> $elementIds
     *
     * @return list<array{id: string, component: string, properties: array<string, mixed>}>
     */
    private function tree(array $elementIds): array
    {
        return array_map(
            static fn (string $id): array => ['id' => $id, 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []],
            $elementIds,
        );
    }

    /**
     * @param list<string> $elementIds
     *
     * @return list<StoredElement>
     */
    private function storedTree(array $elementIds): array
    {
        return array_map(
            static fn (string $id): StoredElement => new StoredElement($id, TestElementTypeLoader::RESOLVABLE),
            $elementIds,
        );
    }

    /**
     * @return list<string>
     */
    private function liveIds(string $layoutId): array
    {
        $layout = $this->repository()->search(new Criteria([$layoutId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(ContentLayoutEntity::class, $layout);

        return array_map(static fn (StoredElement $element): string => $element->id, $layout->getLayout());
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
