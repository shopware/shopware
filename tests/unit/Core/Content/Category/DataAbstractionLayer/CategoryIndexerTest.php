<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryIndexer::class)]
class CategoryIndexerTest extends TestCase
{
    private CategoryIndexer $indexer;

    /**
     * @var Connection&MockObject
     */
    private Connection $connectionMock;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);

        $this->indexer = new CategoryIndexer(
            $this->connectionMock,
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ChildCountUpdater::class),
            $this->createMock(TreeUpdater::class),
            $this->createMock(CategoryBreadcrumbUpdater::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(MessageBusInterface::class),
        );
    }

    /**
     * @param array<string, mixed> $categoryPayload
     * @param array<string, mixed>|null $translationPayload
     * @param list<string>|null $expectedSkips
     */
    #[DataProvider('selectiveIndexingCases')]
    public function testSelectiveIndexing(
        array $categoryPayload,
        ?array $translationPayload,
        string $categoryOperation,
        ?array $expectedSkips,
    ): void {
        $this->connectionMock->method('fetchFirstColumn')->willReturn([]);

        $containerEvent = $this->createCategoryWrittenEvent($categoryPayload, $categoryOperation, $translationPayload);
        $message = $this->indexer->update($containerEvent);

        if ($expectedSkips === null) {
            static::assertNull($message);

            return;
        }

        static::assertNotNull($message);
        static::assertEqualsCanonicalizing($expectedSkips, $message->getSkip());
    }

    /**
     * @return \Generator<string, array{categoryPayload: array<string, mixed>, translationPayload: array<string, mixed>|null, categoryOperation: string, expectedSkips: list<string>|null}>
     */
    public static function selectiveIndexingCases(): \Generator
    {
        yield 'translation: metaDescription only - all updaters skipped' => [
            'categoryPayload' => ['updatedAt' => new \DateTimeImmutable()],
            'translationPayload' => ['metaDescription' => 'new desc'],
            'categoryOperation' => EntityWriteResult::OPERATION_UPDATE,
            'expectedSkips' => null,
        ];

        yield 'translation: name change - breadcrumb only' => [
            'categoryPayload' => ['updatedAt' => new \DateTimeImmutable()],
            'translationPayload' => ['name' => 'New Name'],
            'categoryOperation' => EntityWriteResult::OPERATION_UPDATE,
            'expectedSkips' => [CategoryIndexer::CHILD_COUNT_UPDATER, CategoryIndexer::TREE_UPDATER],
        ];

        yield 'category: parentId change - tree and breadcrumb updaters' => [
            'categoryPayload' => ['parentId' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4'],
            'translationPayload' => null,
            'categoryOperation' => EntityWriteResult::OPERATION_UPDATE,
            'expectedSkips' => [],
        ];

        yield 'category: active state change - at least update seo url' => [
            'categoryPayload' => ['active' => true],
            'translationPayload' => null,
            'categoryOperation' => EntityWriteResult::OPERATION_UPDATE,
            'expectedSkips' => [CategoryIndexer::BREADCRUMB_UPDATER, CategoryIndexer::CHILD_COUNT_UPDATER, CategoryIndexer::TREE_UPDATER],
        ];

        yield 'INSERT - all updaters' => [
            'categoryPayload' => ['parentId' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4'],
            'translationPayload' => null,
            'categoryOperation' => EntityWriteResult::OPERATION_INSERT,
            'expectedSkips' => [],
        ];

        yield 'DELETE - all updaters' => [
            'categoryPayload' => ['id' => 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4'],
            'translationPayload' => null,
            'categoryOperation' => EntityWriteResult::OPERATION_DELETE,
            'expectedSkips' => [],
        ];
    }

    /**
     * @param array<string, mixed> $categoryPayload
     * @param array<string, mixed>|null $translationPayload
     */
    private function createCategoryWrittenEvent(
        array $categoryPayload,
        string $operation,
        ?array $translationPayload = null,
    ): EntityWrittenContainerEvent {
        $uuid = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $categoryPayload['id'] = $uuid;

        $events = new NestedEventCollection();
        $events->add($this->createCategoryEntityWrittenEvent($uuid, $categoryPayload, $operation, $context));

        if ($translationPayload !== null) {
            $events->add($this->createTranslationEntityWrittenEvent($uuid, $translationPayload, $context));
        }

        return new EntityWrittenContainerEvent($context, $events, []);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createCategoryEntityWrittenEvent(
        string $uuid,
        array $payload,
        string $operation,
        Context $context,
    ): EntityWrittenEvent {
        $existence = new EntityExistence(
            CategoryDefinition::ENTITY_NAME,
            ['id' => $uuid],
            $operation !== EntityWriteResult::OPERATION_INSERT,
            false,
            false,
            [],
        );

        $result = new EntityWriteResult(
            $uuid,
            $payload,
            CategoryDefinition::ENTITY_NAME,
            $operation,
            $existence,
        );

        return new EntityWrittenEvent(CategoryDefinition::ENTITY_NAME, [$result], $context);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createTranslationEntityWrittenEvent(
        string $categoryId,
        array $payload,
        Context $context,
    ): EntityWrittenEvent {
        $languageId = Uuid::randomHex();
        $payload['categoryId'] = $categoryId;
        $payload['languageId'] = $languageId;

        $result = new EntityWriteResult(
            ['categoryId' => $categoryId, 'languageId' => $languageId],
            $payload,
            'category_translation',
            EntityWriteResult::OPERATION_UPDATE,
        );

        return new EntityWrittenEvent(CategoryTranslationDefinition::ENTITY_NAME, [$result], $context);
    }
}
