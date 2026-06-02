<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(SearchKeywordUpdater::class)]
class SearchKeywordUpdaterTest extends TestCase
{
    public function testDisabledIndexingSkipsUpdate(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);
        $productRepository = $this->createMock(EntityRepository::class);
        $analyzer = $this->createMock(ProductSearchKeywordAnalyzerInterface::class);

        $languageRepository->expects($this->never())->method('search');
        $productRepository->expects($this->never())->method('search');
        $analyzer->expects($this->never())->method('analyze');

        $updater = new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $languageRepository,
            $productRepository,
            $analyzer,
            false
        );

        $updater->update(['f70db8f6eb884b1ea2a691da3f74dc93'], Context::createDefaultContext());
    }

    public function testAssignParentProductsHydratesParentNameForVariants(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $standaloneId = Uuid::randomHex();

        $child = $this->createProduct($childId, $parentId);
        $standalone = $this->createProduct($standaloneId, null);

        $parent = $this->createProduct($parentId, null);
        $parent->setName('Parent product');
        $parent->setTranslated(['name' => 'Parent product']);

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([
            new ProductCollection([$parent]),
            new ProductCollection(),
        ], new ProductDefinition());

        $updater = $this->createUpdater($productRepository);

        $this->invokeAssignParentProducts(
            $updater,
            [$childId => $child, $standaloneId => $standalone],
            [['field' => 'parent.name']],
        );

        static::assertInstanceOf(ProductEntity::class, $child->getParent());
        static::assertSame('Parent product', $child->getParent()->getName());
        // products without a parent are left untouched
        static::assertNull($standalone->getParent());
    }

    public function testAssignParentProductsDoesNothingWhenFieldNotConfigured(): void
    {
        $childId = Uuid::randomHex();
        $child = $this->createProduct($childId, Uuid::randomHex());

        // empty searches: any call to the repository would throw
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([], new ProductDefinition());

        $updater = $this->createUpdater($productRepository);

        $this->invokeAssignParentProducts(
            $updater,
            [$childId => $child],
            [['field' => 'name'], ['field' => 'description']],
        );

        static::assertNull($child->getParent());
    }

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    private function createUpdater(EntityRepository $productRepository): SearchKeywordUpdater
    {
        return new SearchKeywordUpdater(
            $this->createMock(Connection::class),
            $this->createMock(EntityRepository::class),
            $productRepository,
            $this->createMock(ProductSearchKeywordAnalyzerInterface::class),
        );
    }

    private function createProduct(string $id, ?string $parentId): ProductEntity
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier($id);
        $product->setId($id);
        $product->setParentId($parentId);

        return $product;
    }

    /**
     * @param array<string, ProductEntity> $existingProducts
     * @param array<int, array{field: string}> $configFields
     */
    private function invokeAssignParentProducts(SearchKeywordUpdater $updater, array $existingProducts, array $configFields): void
    {
        $method = new \ReflectionMethod($updater, 'assignParentProducts');
        $method->invoke($updater, $existingProducts, $configFields, Context::createDefaultContext());
    }
}
