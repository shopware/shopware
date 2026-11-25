<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class CategoryIndexerProductCategoryTreeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private IdsCollection $ids;

    private Context $context;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
    }

    public function testMovingACategoryRebuildsTheCategoryTreeOfItsProducts(): void
    {
        $this->categoryRepository->create([
            ['id' => $this->ids->get('old-parent'), 'name' => 'old parent'],
            ['id' => $this->ids->get('new-parent'), 'name' => 'new parent'],
            ['id' => $this->ids->get('moved'), 'name' => 'moved', 'parentId' => $this->ids->get('old-parent')],
            ['id' => $this->ids->get('moved-child'), 'name' => 'moved child', 'parentId' => $this->ids->get('moved')],
        ], $this->context);

        $this->productRepository->create([
            (new ProductBuilder($this->ids, 'product'))
                ->price(100)
                ->category('moved')
                ->variant((new ProductBuilder($this->ids, 'variant'))->build())
                ->build(),
            (new ProductBuilder($this->ids, 'product-in-child'))
                ->price(100)
                ->category('moved-child')
                ->build(),
        ], $this->context);
        $this->runWorker();

        $productIds = array_values($this->ids->getList(['product', 'variant', 'product-in-child']));

        static::assertEqualsCanonicalizing($productIds, $this->fetchProductIdsListedIn('old-parent'));
        static::assertSame([], $this->fetchProductIdsListedIn('new-parent'));

        $this->categoryRepository->update([
            ['id' => $this->ids->get('moved'), 'parentId' => $this->ids->get('new-parent')],
        ], $this->context);
        $this->runWorker();

        static::assertSame([], $this->fetchProductIdsListedIn('old-parent'));
        static::assertEqualsCanonicalizing($productIds, $this->fetchProductIdsListedIn('new-parent'));

        $products = $this->productRepository->search(
            new Criteria($this->ids->getList(['product', 'product-in-child'])),
            $this->context
        )->getEntities();

        static::assertEqualsCanonicalizing(
            array_values($this->ids->getList(['new-parent', 'moved'])),
            array_values($products->get($this->ids->get('product'))?->getCategoryTree() ?? [])
        );
        static::assertEqualsCanonicalizing(
            array_values($this->ids->getList(['new-parent', 'moved', 'moved-child'])),
            array_values($products->get($this->ids->get('product-in-child'))?->getCategoryTree() ?? [])
        );
    }

    /**
     * @return list<string>
     */
    private function fetchProductIdsListedIn(string $categoryKey): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categoriesRo.id', $this->ids->get($categoryKey)));

        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        /** @var list<string> $ids */
        $ids = $this->productRepository->searchIds($criteria, $context)->getIds();

        return $ids;
    }
}
