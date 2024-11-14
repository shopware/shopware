<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(SqlQueryParser::class)]
class SqlQueryParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $repository;

    private EntityRepository $manufacturerRepository;

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');

        $this->context = Context::createDefaultContext();
        $this->repository = $this->getContainer()->get('product.repository');

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

    #[DataProvider('whenToUseNullSafeOperatorProvider')]
    public function testWhenToUseNullSafeOperator(Filter $filter, bool $expected): void
    {
        $parser = $this->getContainer()->get(SqlQueryParser::class);

        $definition = $this->getContainer()->get(ProductDefinition::class);

        $parsed = $parser->parse($filter, $definition, Context::createDefaultContext(), 'product');

        $has = false;
        foreach ($parsed->getWheres() as $where) {
            $has = $has || str_contains((string) $where, '<=>');
        }

        static::assertEquals($expected, $has);
    }

    /**
     * @return iterable<array-key, array{0: Filter, 1: bool}>
     */
    public static function whenToUseNullSafeOperatorProvider()
    {
        yield 'Dont used for simple equals' => [new EqualsFilter('product.id', Uuid::randomHex()), false];
        yield 'Used for negated comparison' => [new NandFilter([new EqualsFilter('product.id', Uuid::randomHex())]), true];
        yield 'Used for negated null comparison' => [new NandFilter([new EqualsFilter('product.id', null)]), true];
        yield 'Used in nested negated comparison' => [new AndFilter([new NandFilter([new EqualsFilter('product.id', Uuid::randomHex())])]), true];
        yield 'Used for null comparison' => [new EqualsFilter('product.id', null), true];
    }

    public function testContainsFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $errournousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', 'target_to_find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testContainsFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $errournousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', 'target%find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testContainsFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target \\ find']);
        $errournousId = $this->createManufacturer(['link' => 'target \\find']);
        $criteria = (new Criteria())->addFilter(new ContainsFilter('link', ' \\ '));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($errournousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', 'target_to'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', 'target%fi'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testPrefixFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => '\\ target find']);
        $erroneousId = $this->createManufacturer(['link' => '\\target find']);
        $criteria = (new Criteria())->addFilter(new PrefixFilter('link', '\\ '));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindUnderscore(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target_to_find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', 'to_find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindPercentageSign(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target%find']);
        $erroneousId = $this->createManufacturer(['link' => 'target to find']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', 'et%find'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    public function testSuffixFilterFindBackslash(): void
    {
        $targetId = $this->createManufacturer(['link' => 'target find \\']);
        $erroneousId = $this->createManufacturer(['link' => 'target find\\']);
        $criteria = (new Criteria())->addFilter(new SuffixFilter('link', ' \\'));
        $foundIds = $this->manufacturerRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertContains($targetId, $foundIds->getIds());
        static::assertNotContains($erroneousId, $foundIds->getIds());
    }

    /**
     * @param array<mixed> $parameters
     */
    private function createManufacturer(array $parameters = []): string
    {
        $id = Uuid::randomHex();

        $defaults = ['id' => $id, 'name' => 'Test'];

        $parameters = array_merge($defaults, $parameters);

        $this->manufacturerRepository->create([$parameters], Context::createDefaultContext());

        return $id;
    }

    private function createProduct(): void
    {
        $products = [
            (new ProductBuilder($this->ids, 'product1-with-category', 10))
                ->categories(['category1', 'category2'])
                ->visibility()
                ->price(10)
                ->build(),
            (new ProductBuilder($this->ids, 'product2-with-category', 12))
                ->category('category2')
                ->visibility()
                ->price(20)
                ->build(),
            (new ProductBuilder($this->ids, 'product1-without-category', 14))
                ->visibility()
                ->price(30)
                ->build(),
            (new ProductBuilder($this->ids, 'product2-without-category', 16))
                ->visibility()
                ->price(40)
                ->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());
    }
}
