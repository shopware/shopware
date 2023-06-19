<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser
 */
class SqlQueryParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->repository = $this->getContainer()->get('product.repository');
        $this->context = Context::createDefaultContext();

        $this->ids = new IdsCollection();

        $this->createProduct();

        parent::setUp();
    }

    public function testFindProductsWithoutCategory(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryIds', null));

        $result = $this->repository->searchIds($criteria, $this->context);

        $productsWithoutCategory = [
            $this->ids->get('product1-without-category'),
            $this->ids->get('product2-without-category'),
        ];

        static::assertEquals($productsWithoutCategory, $result->getIds());
    }

    public function testFindProductsWithCategory(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('categoryIds', $this->ids->get('category1')));

        $result = $this->repository->searchIds($criteria, $this->context);

        $productsWithoutCategory = [
            $this->ids->get('product1-with-category'),
        ];

        static::assertEquals($productsWithoutCategory, $result->getIds());
    }

    private function createProduct(): void
    {
        (new ProductBuilder($this->ids, 'product1-with-category', 10))
            ->categories(['category1', 'category2'])
            ->visibility()->price(10)->write($this->getContainer());
        (new ProductBuilder($this->ids, 'product2-with-category', 12))
            ->category('category2')
            ->visibility()->price(20)->write($this->getContainer());

        (new ProductBuilder($this->ids, 'product1-without-category', 14))
            ->visibility()->price(30)->write($this->getContainer());
        (new ProductBuilder($this->ids, 'product2-without-category', 16))
            ->visibility()->price(40)->write($this->getContainer());
    }
}
