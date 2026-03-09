<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CategoryIndexerVersioningTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private CategoryIndexer $categoryIndexer;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->categoryIndexer = static::getContainer()->get(CategoryIndexer::class);
    }

    public function testUpdateIndexesOnlyCategoriesReachableInCurrentVersionTree(): void
    {
        // live root category
        $context = Context::createDefaultContext();
        $rootCategoryId = $this->createCategory($context);

        // category existing in live and draft
        $childCategoryId = $this->createCategory($context, $rootCategoryId);

        $draftVersionId = $this->categoryRepository->createVersion($childCategoryId, $context);
        $draftContext = $context->createWithVersionId($draftVersionId);

        // draft-only sibling under rootCategoryId - not reachable from this update seed path - should NOT be indexed
        $childCategory2Id = $this->createCategory($draftContext, $rootCategoryId);

        // descendant of skipped draft-only branch - should NOT be indexed
        $childCategory3Id = $this->createCategory($draftContext, $childCategory2Id, $draftVersionId);

        // Descendant of the written category in the current draft context - should be indexed
        $childCategory4Id = $this->createCategory($draftContext, $childCategoryId);

        $event = $this->categoryRepository->update([
            [
                'id' => $childCategoryId,
                'name' => 'Changed ' . Uuid::randomHex(),
            ],
        ], $draftContext);

        $message = $this->categoryIndexer->update($event);
        static::assertInstanceOf(EntityIndexingMessage::class, $message);

        $ids = $message->getData();
        static::assertIsArray($ids);

        // Included from EntityExistence old parent_id, not via recursive draft descendant lookup
        static::assertContains($rootCategoryId, $ids);

        static::assertContains($childCategoryId, $ids);
        static::assertContains($childCategory4Id, $ids);
        static::assertNotContains($childCategory2Id, $ids);
        static::assertNotContains($childCategory3Id, $ids);
    }

    private function createCategory(Context $context, ?string $parentId = null, ?string $parentVersionId = null): string
    {
        $id = Uuid::randomHex();

        $payload = [
            'id' => $id,
            'name' => 'Category ' . $id,
        ];

        if ($parentId !== null) {
            $payload['parentId'] = $parentId;
        }

        if ($parentVersionId !== null) {
            $payload['parentVersionId'] = $parentVersionId;
        }

        $this->categoryRepository->create([$payload], $context);

        return $id;
    }
}
