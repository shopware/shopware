<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout\Revision;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionService;
use Shopware\Core\Framework\ContentSystem\Layout\Revision\LayoutRevisionStore;
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
#[CoversClass(LayoutRevisionService::class)]
#[CoversClass(LayoutRevisionStore::class)]
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
        static::assertSame($first->published, $second->published);
        static::assertNotNull($second->revision($second->published)?->publishedAt);
    }

    #[TestDox('rejects a save whose expected head is no longer the head of the branch')]
    public function testSaveRejectsAStaleHead(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $branch = $this->service->createBranch($layoutId);

        $this->service->saveRevision($layoutId, $branch->id, $this->tree(['draft-a']), $branch->head, null, $this->context);

        $this->assertConflict(
            ContentSystemException::LAYOUT_VERSION_CONFLICT,
            fn () => $this->service->saveRevision($layoutId, $branch->id, $this->tree(['draft-b']), $branch->head, null, $this->context),
        );
    }

    #[TestDox('publishing a draft writes its tree to the layout column, moves the pointer and removes the draft')]
    public function testPublishingADraft(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $branch = $this->service->createBranch($layoutId);
        $saved = $this->service->saveRevision($layoutId, $branch->id, $this->tree(['draft-a', 'draft-b']), $branch->head, null, $this->context);

        $this->service->publish($layoutId, $saved['revision']->id, $branch->id, null, $this->context);

        static::assertSame(['draft-a', 'draft-b'], $this->liveIds($layoutId));
        static::assertSame($saved['revision']->id, $this->service->history($layoutId)->published);
        static::assertSame([], $this->service->listBranches($layoutId));
    }

    #[TestDox('publishing a draft fails without writing once another draft went live after it was opened')]
    public function testPublishingAStaleDraftConflicts(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $first = $this->service->createBranch($layoutId);
        $second = $this->service->createBranch($layoutId);
        $firstSaved = $this->service->saveRevision($layoutId, $first->id, $this->tree(['first']), $first->head, null, $this->context);
        $secondSaved = $this->service->saveRevision($layoutId, $second->id, $this->tree(['second']), $second->head, null, $this->context);

        $this->service->publish($layoutId, $secondSaved['revision']->id, $second->id, null, $this->context);

        $this->assertConflict(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $firstSaved['revision']->id, $first->id, null, $this->context),
        );
        static::assertSame(['second'], $this->liveIds($layoutId));
        static::assertCount(1, $this->service->listBranches($layoutId));
    }

    #[TestDox('a layout write that bypasses publishing becomes the published revision, so a draft opened before it cannot revert it')]
    public function testDirectLayoutWriteIsAdopted(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->published;
        $draft = $this->service->createBranch($layoutId);
        $saved = $this->service->saveRevision($layoutId, $draft->id, $this->tree(['draft']), $draft->head, null, $this->context);

        $this->repository()->update([['id' => $layoutId, 'layout' => $this->tree(['direct'])]], $this->context);

        $history = $this->service->history($layoutId);
        $adopted = $history->revision($history->published);
        static::assertNotSame($initial, $history->published);
        static::assertSame($initial, $adopted?->parent);

        $this->assertConflict(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $saved['revision']->id, $draft->id, null, $this->context),
        );
        static::assertSame(['direct'], $this->liveIds($layoutId));
        static::assertSame($history->published, $this->service->createBranch($layoutId)->base);
    }

    #[TestDox('publishing from the history requires the live state the caller last saw')]
    public function testPublishChecksTheExpectedPublishedRevision(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->published;
        $branch = $this->service->createBranch($layoutId);
        $saved = $this->service->saveRevision($layoutId, $branch->id, $this->tree(['draft']), $branch->head, null, $this->context);
        $this->service->publish($layoutId, $saved['revision']->id, $branch->id, null, $this->context);

        $this->assertConflict(
            ContentSystemException::LAYOUT_PUBLISH_CONFLICT,
            fn () => $this->service->publish($layoutId, $initial, null, $initial, $this->context),
        );

        $this->service->publish($layoutId, $initial, null, $saved['revision']->id, $this->context);

        static::assertSame(['live-a'], $this->liveIds($layoutId));
        static::assertSame($initial, $this->service->history($layoutId)->published);
    }

    #[TestDox('a draft opened from an older revision can be published while the live state is the one it was opened against')]
    public function testDraftOpenedFromHistoryCanBePublished(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $initial = $this->service->history($layoutId)->published;
        $branch = $this->service->createBranch($layoutId);
        $saved = $this->service->saveRevision($layoutId, $branch->id, $this->tree(['newer']), $branch->head, null, $this->context);
        $this->service->publish($layoutId, $saved['revision']->id, $branch->id, null, $this->context);

        $restore = $this->service->createBranch($layoutId, null, $initial);
        $this->service->publish($layoutId, $restore->head, $restore->id, null, $this->context);

        static::assertSame(['live-a'], $this->liveIds($layoutId));
    }

    #[TestDox('deleting a draft prunes the revisions only it reached and keeps every published one')]
    public function testDeletingADraftPrunesItsRevisions(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $published = $this->service->createBranch($layoutId);
        $publishedSaved = $this->service->saveRevision($layoutId, $published->id, $this->tree(['published']), $published->head, null, $this->context);
        $this->service->publish($layoutId, $publishedSaved['revision']->id, $published->id, null, $this->context);

        $draft = $this->service->createBranch($layoutId);
        $first = $this->service->saveRevision($layoutId, $draft->id, $this->tree(['one']), $draft->head, null, $this->context);
        $this->service->saveRevision($layoutId, $draft->id, $this->tree(['two']), $first['branch']->head, null, $this->context);

        static::assertCount(4, $this->service->history($layoutId)->revisions);

        $this->service->deleteBranch($layoutId, $draft->id);

        $history = $this->service->history($layoutId);
        static::assertCount(2, $history->revisions);
        static::assertSame($publishedSaved['revision']->id, $history->published);
    }

    #[TestDox('the revision rows of a layout are removed with the layout')]
    public function testRevisionsAreDeletedWithTheLayout(): void
    {
        $layoutId = $this->createLayout(['live-a']);
        $branch = $this->service->createBranch($layoutId);
        $this->service->saveRevision($layoutId, $branch->id, $this->tree(['draft']), $branch->head, null, $this->context);

        $this->repository()->delete([['id' => $layoutId]], $this->context);

        $connection = static::getContainer()->get(Connection::class);
        foreach (['content_layout_revision', 'content_layout_branch', 'content_layout_publication'] as $table) {
            static::assertSame(0, (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM `' . $table . '` WHERE `content_layout_id` = :id',
                ['id' => Uuid::fromHexToBytes($layoutId)],
            ), $table);
        }
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
