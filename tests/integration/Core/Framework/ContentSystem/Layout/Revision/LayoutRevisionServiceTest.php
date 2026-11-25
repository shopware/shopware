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

        $this->assertConflict(
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

        $this->assertConflict(
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

        $this->assertConflict(
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

        $this->assertConflict(
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

    #[TestDox('discarding a draft prunes the revisions only it reached and keeps every published one')]
    public function testDiscardingADraftPrunesItsRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $published = $this->service->createDraft($layoutId);
        $publishedSaved = $this->save($layoutId, $published->id, ['published'], $published->head);
        $this->service->publish($layoutId, $published->id, $publishedSaved['draft']->head, $this->context);

        $draft = $this->service->createDraft($layoutId);
        $first = $this->save($layoutId, $draft->id, ['one'], $draft->head);
        $this->save($layoutId, $draft->id, ['two'], $first['draft']->head);

        static::assertCount(4, $this->service->history($layoutId)->revisions);

        $this->service->discardDraft($layoutId, $draft->id);

        $history = $this->service->history($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($publishedSaved['revision']->id, $history->live);
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

        $this->assertConflict(
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
     * @param \Closure(): mixed $operation
     */
    private function assertConflict(string $errorCode, \Closure $operation): void
    {
        try {
            $operation();
        } catch (ContentSystemException $exception) {
            static::assertSame($errorCode, $exception->getErrorCode());

            return;
        }

        static::fail('Expected a ' . $errorCode . ' conflict.');
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
