<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The amount of re-indexing a single category write triggers must scale with
 * that category's own subtree, not with the size of its parent's subtree. A
 * parent is only ever relevant for child-count recomputation and must never be
 * used as an expansion base, otherwise unrelated siblings get dragged in and
 * re-indexed, producing a large amount of redundant SQL.
 *
 * @internal
 */
class CategoryIndexerQueryAmplificationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CategoryIndexer $indexer;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');

        $this->indexer = new CategoryIndexer(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(IteratorFactory::class),
            $this->categoryRepository,
            static::getContainer()->get(ChildCountUpdater::class),
            static::getContainer()->get(TreeUpdater::class),
            static::getContainer()->get(CategoryBreadcrumbUpdater::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(MessageBusInterface::class)
        );
    }

    public function testNameChangeOnLeafDoesNotReindexSiblings(): void
    {
        $context = Context::createDefaultContext();

        $parentId = Uuid::randomHex();
        $editedLeafId = Uuid::randomHex();
        $siblingIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        $this->createTree($context, $parentId, [$editedLeafId, ...$siblingIds]);

        $event = $this->buildNameChangedEvent($context, $editedLeafId, $parentId);

        $reindexedIds = $this->collectReindexedIds($event);

        static::assertContains($editedLeafId, $reindexedIds);
        foreach ($siblingIds as $siblingId) {
            static::assertNotContains(
                $siblingId,
                $reindexedIds,
                'Sibling category was pulled into re-indexing by an unrelated name change.'
            );
        }
    }

    public function testInsertDoesNotReindexExistingSiblings(): void
    {
        $context = Context::createDefaultContext();

        $parentId = Uuid::randomHex();
        $newChildId = Uuid::randomHex();
        $existingSiblingIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        // Create the parent with its existing children, plus the "new" child so
        // the real tree/children queries inside the indexer have data to read.
        $this->createTree($context, $parentId, [$newChildId, ...$existingSiblingIds]);

        $event = $this->buildInsertEvent($context, $newChildId, $parentId);

        $reindexedIds = $this->collectReindexedIds($event);

        static::assertContains($newChildId, $reindexedIds);
        static::assertContains($parentId, $reindexedIds, 'Parent must be re-indexed so its child count is recomputed.');
        foreach ($existingSiblingIds as $siblingId) {
            static::assertNotContains(
                $siblingId,
                $reindexedIds,
                'Existing sibling category was pulled into re-indexing by inserting a new child.'
            );
        }
    }

    public function testNameChangeReindexesOwnDescendants(): void
    {
        $context = Context::createDefaultContext();

        $parentId = Uuid::randomHex();
        $editedId = Uuid::randomHex();
        $siblingId = Uuid::randomHex();
        $grandChildId = Uuid::randomHex();

        $this->categoryRepository->create([
            [
                'id' => $parentId,
                'name' => 'Parent',
                'children' => [
                    [
                        'id' => $editedId,
                        'name' => 'Edited',
                        'children' => [['id' => $grandChildId, 'name' => 'Grandchild']],
                    ],
                    ['id' => $siblingId, 'name' => 'Sibling'],
                ],
            ],
        ], $context);

        $event = $this->buildNameChangedEvent($context, $editedId, $parentId);

        $reindexedIds = $this->collectReindexedIds($event);

        static::assertContains($editedId, $reindexedIds);
        static::assertContains(
            $grandChildId,
            $reindexedIds,
            'A renamed category\'s descendants must be re-indexed because their breadcrumb embeds the ancestor name.'
        );
        static::assertNotContains($siblingId, $reindexedIds);
    }

    /**
     * @param list<string> $childIds
     */
    private function createTree(Context $context, string $parentId, array $childIds): void
    {
        $this->categoryRepository->create([
            [
                'id' => $parentId,
                'name' => 'Parent',
                'children' => array_map(
                    static fn (string $id, int $i): array => ['id' => $id, 'name' => 'Child ' . $i],
                    $childIds,
                    array_keys($childIds)
                ),
            ],
        ], $context);
    }

    /**
     * @return list<string>
     */
    private function collectReindexedIds(EntityWrittenContainerEvent $event): array
    {
        $message = $this->indexer->update($event);

        static::assertNotNull($message);
        $ids = $message->getData();
        static::assertIsArray($ids);

        return array_values($ids);
    }

    private function buildNameChangedEvent(
        Context $context,
        string $categoryId,
        string $parentId
    ): EntityWrittenContainerEvent {
        $existence = new EntityExistence(
            CategoryDefinition::ENTITY_NAME,
            ['id' => $categoryId],
            true,
            true,
            false,
            ['parent_id' => Uuid::fromHexToBytes($parentId)]
        );

        $categoryResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId, 'updatedAt' => new \DateTimeImmutable()],
            CategoryDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
            $existence
        );

        $languageId = Uuid::randomHex();
        $translationResult = new EntityWriteResult(
            ['categoryId' => $categoryId, 'languageId' => $languageId],
            ['categoryId' => $categoryId, 'languageId' => $languageId, 'name' => 'Changed name'],
            CategoryTranslationDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        );

        $events = new NestedEventCollection();
        $events->add(new EntityWrittenEvent(CategoryDefinition::ENTITY_NAME, [$categoryResult], $context));
        $events->add(new EntityWrittenEvent(CategoryTranslationDefinition::ENTITY_NAME, [$translationResult], $context));

        return new EntityWrittenContainerEvent($context, $events, []);
    }

    private function buildInsertEvent(
        Context $context,
        string $categoryId,
        string $parentId
    ): EntityWrittenContainerEvent {
        $existence = new EntityExistence(
            CategoryDefinition::ENTITY_NAME,
            ['id' => $categoryId],
            false,
            true,
            false,
            []
        );

        $categoryResult = new EntityWriteResult(
            $categoryId,
            ['id' => $categoryId, 'parentId' => $parentId],
            CategoryDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
            $existence
        );

        $events = new NestedEventCollection();
        $events->add(new EntityWrittenEvent(CategoryDefinition::ENTITY_NAME, [$categoryResult], $context));

        return new EntityWrittenContainerEvent($context, $events, []);
    }
}
