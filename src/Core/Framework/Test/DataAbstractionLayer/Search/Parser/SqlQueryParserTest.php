<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Parser;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
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

/**
 * @internal
 */
class SqlQueryParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $manufacturerRepository;

    protected function setUp(): void
    {
        $this->manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
    }

    /**
     * @dataProvider whenToUseNullSafeOperatorProvider
     */
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

    private function createManufacturer(array $parameters = []): string
    {
        $id = Uuid::randomHex();

        $defaults = ['id' => $id, 'name' => 'Test'];

        $parameters = array_merge($defaults, $parameters);

        $this->manufacturerRepository->create([$parameters], Context::createDefaultContext());

        return $id;
    }
}
