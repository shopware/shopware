<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class CategoryRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('category.repository');
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testDeleteParentCategoryDeletesSubCategories(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId]],
            Context::createDefaultContext()
        );

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertSame(
            [$parentId, $childId],
            $event->getIds()
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(0, $exists);
    }

    public function testDeleteChildCategory(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $childId]],
            Context::createDefaultContext()
        );

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);
        static::assertSame([$childId], $event->getIds());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(0, $exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertNotCount(0, $exists);
    }

    public function testWriterConsidersDeleteParent(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete([
            ['id' => $parentId],
        ], Context::createDefaultContext());

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertContains($parentId, $event->getIds());
        static::assertContains($childId, $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testCreateNesting(): void
    {
        $parent = Uuid::randomHex();
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();
        $recordC = Uuid::randomHex();

        $categories = [
            ['id' => $parent, 'name' => 'First Level Category'],
            ['id' => $recordA, 'name' => 'Second Level Category', 'parentId' => $parent],
            ['id' => $recordC, 'name' => 'Third Level Category', 'parentId' => $recordA],
            ['id' => $recordB, 'name' => 'Second Level Category 2', 'parentId' => $parent, 'afterCategoryId' => $recordA],
        ];

        $this->repository->create($categories, Context::createDefaultContext());

        $criteria = new Criteria([$parent]);
        $criteria->addAssociation('children');

        $result = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        $first = $result->first();
        static::assertNotNull($first);

        // First Level Category should have Level 1
        static::assertSame($parent, $first->getId());
        static::assertSame(1, $first->getLevel());

        // Second Level Categories should have Level 2
        $children = $first->getChildren();
        static::assertNotNull($children);
        $children->sortByPosition();
        $childrenArray = array_values($children->getElements());
        static::assertSame($recordA, $childrenArray[0]->getId());
        static::assertSame(2, $childrenArray[0]->getLevel());
        static::assertSame($recordB, $childrenArray[1]->getId());
        static::assertSame(2, $childrenArray[1]->getLevel());

        $criteria = new Criteria([$recordA]);
        $criteria->addAssociation('children');

        $result = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        $firstCategory = $result->first();
        static::assertNotNull($firstCategory);

        // Second Level Category should have Level 2
        static::assertSame($recordA, $firstCategory->getId());
        static::assertSame(2, $firstCategory->getLevel());

        // Third Level Category should have Level 3
        $children = $firstCategory->getChildren();
        static::assertNotNull($children);
        $firstChild = $children->first();
        static::assertNotNull($firstChild);
        static::assertSame($recordC, $firstChild->getId());
        static::assertSame(3, $firstChild->getLevel());
    }

    #[DataProvider('seoFieldProvider')]
    public function testSeoFieldLongerThan255CharactersIsRejected(string $field): void
    {
        $id = Uuid::randomHex();

        try {
            $this->repository->create([
                ['id' => $id, 'name' => 'test', $field => str_repeat('a', 256)],
            ], Context::createDefaultContext());

            static::fail('A value longer than 255 characters must be rejected');
        } catch (WriteException $e) {
            $errors = iterator_to_array($e->getErrors());
            static::assertCount(1, $errors);
            static::assertStringEndsWith('/' . $field, $errors[0]['source']['pointer']);
        }

        $this->repository->create([
            ['id' => $id, 'name' => 'test', $field => str_repeat('a', 255)],
        ], Context::createDefaultContext());

        $entity = $this->repository->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($entity);
        static::assertSame(str_repeat('a', 255), $entity->get($field));
    }

    public function testUnknownTypeIsRejectedOnCreate(): void
    {
        $id = Uuid::randomHex();

        $this->assertTypeIsRejected(static fn (EntityRepository $repository) => $repository->create([
            ['id' => $id, 'name' => 'test', 'type' => 'remove'],
        ], Context::createDefaultContext()));

        static::assertFalse($this->categoryExists($id));
    }

    public function testUnknownTypeIsRejectedOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([['id' => $id, 'name' => 'test']], Context::createDefaultContext());

        $this->assertTypeIsRejected(static fn (EntityRepository $repository) => $repository->update([
            ['id' => $id, 'type' => 'remove'],
        ], Context::createDefaultContext()));

        static::assertSame(CategoryDefinition::TYPE_PAGE, $this->fetchType($id));
    }

    public function testUnknownTypeIsRejectedOnAssociatedCategory(): void
    {
        $categoryId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $this->repository->create([['id' => $categoryId, 'name' => 'test']], Context::createDefaultContext());

        try {
            static::getContainer()->get('product.repository')->create([[
                'id' => $productId,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 19],
                'categories' => [['id' => $categoryId, 'type' => 'remove']],
            ]], Context::createDefaultContext());

            static::fail('An unknown category type must be rejected');
        } catch (WriteException $e) {
            $errors = iterator_to_array($e->getErrors());
            static::assertCount(1, $errors);
            static::assertSame('/0/categories/0/category/type', $errors[0]['source']['pointer']);
        }

        static::assertSame(CategoryDefinition::TYPE_PAGE, $this->fetchType($categoryId));
    }

    #[DataProvider('knownTypeProvider')]
    public function testKnownTypesAreAccepted(string $type): void
    {
        $id = Uuid::randomHex();

        $this->repository->create([
            ['id' => $id, 'name' => 'test', 'type' => $type, 'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL],
        ], Context::createDefaultContext());

        static::assertSame($type, $this->fetchType($id));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function knownTypeProvider(): iterable
    {
        yield 'page' => [CategoryDefinition::TYPE_PAGE];
        yield 'link' => [CategoryDefinition::TYPE_LINK];
        yield 'folder' => [CategoryDefinition::TYPE_FOLDER];
    }

    public function testCategoryWithStoredUnknownTypeCanStillBeUpdated(): void
    {
        $id = Uuid::randomHex();
        $this->repository->create([['id' => $id, 'name' => 'test']], Context::createDefaultContext());
        $this->connection->executeStatement(
            'UPDATE category SET type = :type WHERE id = :id',
            ['type' => 'remove', 'id' => Uuid::fromHexToBytes($id)],
        );

        $this->repository->update([['id' => $id, 'active' => false]], Context::createDefaultContext());

        static::assertSame('remove', $this->fetchType($id));

        $this->repository->update([['id' => $id, 'type' => CategoryDefinition::TYPE_FOLDER]], Context::createDefaultContext());

        static::assertSame(CategoryDefinition::TYPE_FOLDER, $this->fetchType($id));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function seoFieldProvider(): iterable
    {
        yield 'meta title' => ['metaTitle'];
        yield 'meta description' => ['metaDescription'];
        yield 'keywords' => ['keywords'];
    }

    /**
     * @param \Closure(EntityRepository<CategoryCollection>): mixed $write
     */
    private function assertTypeIsRejected(\Closure $write): void
    {
        try {
            $write($this->repository);

            static::fail('An unknown category type must be rejected');
        } catch (WriteException $e) {
            $errors = iterator_to_array($e->getErrors());
            static::assertCount(1, $errors);
            static::assertSame('/0/type', $errors[0]['source']['pointer']);
        }
    }

    private function fetchType(string $id): string|false
    {
        return $this->connection->fetchOne(
            'SELECT type FROM category WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)],
        );
    }

    private function categoryExists(string $id): bool
    {
        return $this->fetchType($id) !== false;
    }
}
