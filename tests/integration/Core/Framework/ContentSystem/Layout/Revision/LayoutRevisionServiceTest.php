<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutDraft;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevision;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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

    #[TestDox('adopts the layout column as the single published revision on first access, however often it is read')]
    public function testFirstAccessAdoptsTheLayoutColumn(): void
    {
        $layoutId = $this->createLayout(['live-a']);

        $first = $this->service->history($layoutId);
        $second = $this->service->history($layoutId);

        static::assertCount(1, $second->revisions);
        static::assertSame($first->live, $second->live);
        static::assertNotNull($second->revision($second->live)?->publishedAt);
    }

    #[TestDox('publishing a draft writes its head to the layout column, moves the live pointer and removes the draft')]
    public function testPublishingADraft(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
        $saved = $this->save($layoutId, $draft->id, ['draft-a', 'draft-b'], $draft->head);

        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);

        $history = $this->service->history($layoutId);
        static::assertSame(['draft-a', 'draft-b'], $this->liveIds($layoutId));
        static::assertSame($saved['revision']->id, $history->live);
        static::assertCount(2, $history->revisions);
        static::assertSame([], $this->service->listDrafts($layoutId));
    }

    #[TestDox('publishing a head the caller has not seen fails without writing and keeps the draft')]
    public function testPublishingAStaleHeadConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
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
        $first = $this->service->createDraft($layoutId);
        $second = $this->service->createDraft($layoutId);
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
        $initial = $this->service->history($layoutId)->live;
        $draft = $this->service->createDraft($layoutId);
        $saved = $this->save($layoutId, $draft->id, ['draft'], $draft->head);

        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);

        $history = $this->service->history($layoutId);
        $adopted = $history->revision($history->live);
        static::assertNotSame($initial, $history->live);
        static::assertSame($initial, $adopted?->parent);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context),
        );
        static::assertSame(['direct'], $this->liveIds($layoutId));
        static::assertSame($history->live, $this->service->createDraft($layoutId)->base);
    }

    #[TestDox('a draft opened from an older revision rolls the layout back and the live pointer follows it')]
    public function testDraftOpenedFromHistoryCanBePublished(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->live;
        $draft = $this->service->createDraft($layoutId);
        $saved = $this->save($layoutId, $draft->id, ['newer'], $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);

        $restore = $this->service->createDraft($layoutId, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->context);

        $history = $this->service->history($layoutId);
        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($initial, $history->live);
        static::assertCount(2, $history->revisions);
    }

    #[TestDox('publishing a draft opened before a rollback fails without writing, although the draft descends from the restored revision')]
    public function testPublishingADraftOpenedBeforeARollbackConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->live;
        $edit = $this->service->createDraft($layoutId);
        $editSaved = $this->save($layoutId, $edit->id, ['newer'], $edit->head);
        $this->service->publish($layoutId, $edit->id, $editSaved['draft']->head, $this->context);

        $stale = $this->service->createDraft($layoutId);
        $staleSaved = $this->save($layoutId, $stale->id, ['newer', 'stale'], $stale->head);

        $restore = $this->service->createDraft($layoutId, fromRevisionId: $initial);
        $this->service->publish($layoutId, $restore->id, $restore->head, $this->context);

        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $stale->id, $staleSaved['draft']->head, $this->context),
        );
        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($initial, $this->service->history($layoutId)->live);
        static::assertCount(1, $this->service->listDrafts($layoutId));
    }

    #[TestDox('a draft branched from another draft can be published after it, since live only moved forward into the draft')]
    public function testDraftBranchedFromAnotherDraftCanBePublishedAfterIt(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $first = $this->service->createDraft($layoutId);
        $firstSaved = $this->save($layoutId, $first->id, ['first'], $first->head);

        $branched = $this->service->createDraft($layoutId, fromRevisionId: $firstSaved['draft']->head);
        $branchedSaved = $this->save($layoutId, $branched->id, ['first', 'second'], $branched->head);

        $this->service->publish($layoutId, $first->id, $firstSaved['draft']->head, $this->context);
        $this->service->publish($layoutId, $branched->id, $branchedSaved['draft']->head, $this->context);

        static::assertSame(['first', 'second'], $this->liveIds($layoutId));
        static::assertSame($branchedSaved['revision']->id, $this->service->history($layoutId)->live);
    }

    #[TestDox('discarding a draft deletes its head and keeps every published revision')]
    public function testDiscardingADraftDeletesItsRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $published = $this->service->createDraft($layoutId);
        $publishedSaved = $this->save($layoutId, $published->id, ['published'], $published->head);
        $this->service->publish($layoutId, $published->id, $publishedSaved['draft']->head, $this->context);

        $draft = $this->service->createDraft($layoutId);
        $first = $this->save($layoutId, $draft->id, ['one'], $draft->head);
        $this->save($layoutId, $draft->id, ['two'], $first['draft']->head);

        static::assertCount(3, $this->service->history($layoutId)->revisions);

        $this->service->discardDraft($layoutId, $draft->id);

        $history = $this->service->history($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($publishedSaved['revision']->id, $history->live);
    }

    #[TestDox('a draft keeps only its head, attached to the revision it started from')]
    public function testADraftKeepsOnlyItsHead(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
        $head = $draft->head;
        foreach (['one', 'two', 'three'] as $elementId) {
            $head = $this->save($layoutId, $draft->id, [$elementId], $head)['draft']->head;
        }

        $history = $this->service->history($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($draft->base, $history->revision($head)?->parent);
    }

    #[TestDox('publishing keeps the 20 most recently published revisions, so an older one can no longer be restored')]
    public function testPublishingKeepsTheLatestPublishedRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->live;
        for ($i = 1; $i <= 20; ++$i) {
            $this->publishEdit($layoutId, ['edit-' . $i]);
        }

        $history = $this->service->history($layoutId);
        static::assertCount(20, $history->revisions);
        static::assertNull($history->revision($initial));
        $this->assertFailsWith(
            ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND,
            fn () => $this->service->createDraft($layoutId, fromRevisionId: $initial),
        );
    }

    #[TestDox('an open draft keeps the live revision it was opened from beyond the published window, so publishing it still conflicts')]
    public function testAnOpenDraftKeepsItsLiveAtOpenRevision(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->live;
        $stale = $this->service->createDraft($layoutId);
        $staleSaved = $this->save($layoutId, $stale->id, ['stale'], $stale->head);
        for ($i = 1; $i <= 20; ++$i) {
            $this->publishEdit($layoutId, ['edit-' . $i]);
        }

        static::assertNotNull($this->service->history($layoutId)->revision($initial));
        $this->assertFailsWith(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $stale->id, $staleSaved['draft']->head, $this->context),
        );
    }

    #[TestDox('a draft branches from another draft\'s current head only, since its earlier heads are deleted')]
    public function testBranchingFromASupersededHeadIsNotFound(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
        $first = $this->save($layoutId, $draft->id, ['one'], $draft->head);
        $this->save($layoutId, $draft->id, ['two'], $first['draft']->head);

        $this->assertFailsWith(
            ContentSystemException::CONTENT_LAYOUT_REVISION_NOT_FOUND,
            fn () => $this->service->createDraft($layoutId, fromRevisionId: $first['draft']->head),
        );
    }

    #[TestDox('listing and reading drafts never write: a layout write past publishing is adopted only by the next write')]
    public function testReadingDraftsDoesNotReconcile(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);
        $rowsBefore = $this->revisionRows($layoutId);

        static::assertSame([$draft->id], array_map(static fn (LayoutDraft $listed): string => $listed->id, $this->service->listDrafts($layoutId)));
        $read = $this->service->getDraft($layoutId, $draft->id);

        static::assertSame($draft->head, $read['draft']->head);
        static::assertSame(['live-a'], array_map(static fn (StoredElement $element): string => $element->id, $read['layout']));
        static::assertSame($rowsBefore, $this->revisionRows($layoutId));

        $this->service->createDraft($layoutId);
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

    #[TestDox('the revision rows of a layout are removed with the layout')]
    public function testRevisionsAreDeletedWithTheLayout(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $draft = $this->service->createDraft($layoutId);
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
        $draft = $this->service->createDraft($layoutId);
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
        $draft = $this->service->createDraft($layoutId);
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
        $draft = $this->service->createDraft($layoutId);

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
     * @param list<string> $elementIds
     */
    private function publishEdit(string $layoutId, array $elementIds): void
    {
        $draft = $this->service->createDraft($layoutId);
        $saved = $this->save($layoutId, $draft->id, $elementIds, $draft->head);
        $this->service->publish($layoutId, $draft->id, $saved['draft']->head, $this->context);
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
