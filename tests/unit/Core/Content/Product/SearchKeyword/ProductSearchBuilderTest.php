<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Content\Product\SearchKeyword\ScoredMatchesMarker;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSearchBuilder::class)]
class ProductSearchBuilderTest extends TestCase
{
    private const LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    public function testFallbackToCriteriaTermWhenSearchKeywordIndexingIsDisabled(): void
    {
        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $logger = static::createStub(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20,
            false
        );

        $mockSalesChannelContext = static::createStub(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();
        $request->query->set('search', 'ring saphir');

        $termInterpreter->expects($this->never())->method('interpret');

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        static::assertSame('ring saphir', $criteria->getTerm());
        static::assertFalse(self::hasScoredMatchesMarker($criteria));
    }

    public function testSearchTermMaxLengthReached(): void
    {
        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $mockSalesChannelContext = static::createStub(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();

        $request->query->set('search', 'This search term\'s length is over 20 characters');

        $logger
            ->expects($this->once())
            ->method('notice')
            ->with(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                [
                    'term' => 'This search term\'s length is over 20 characters',
                    'maxLength' => 20,
                ]
            );
        $termInterpreter->expects($this->once())
            ->method('interpret')
            ->with('This search term\'s l', static::isInstanceOf(Context::class));
        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);
    }

    public function testOrLogicRestrictsKeywordFilterToPositiveRankings(): void
    {
        $criteria = $this->build(new SearchPattern(new SearchTerm('saphir')));

        $expectedFilter = new AndFilter([
            new EqualsAnyFilter('product.searchKeywords.keyword', ['saphir', 'saphir ring']),
            new EqualsFilter('product.searchKeywords.languageId', self::LANGUAGE_ID),
            new RangeFilter('product.searchKeywords.ranking', [RangeFilter::GT => 0]),
        ]);
        $expectedFilter->addExtension(ScoredMatchesMarker::EXTENSION_NAME, new ScoredMatchesMarker(2));
        static::assertEquals([$expectedFilter], $criteria->getFilters());

        $marker = $criteria->getFilters()[0]->getExtension(ScoredMatchesMarker::EXTENSION_NAME);
        static::assertCount(2, $criteria->getQueries());
        foreach ($criteria->getQueries() as $query) {
            static::assertSame($marker, $query->getExtension(ScoredMatchesMarker::EXTENSION_NAME));
        }
    }

    public function testAndLogicKeepsTokenFiltersUnchanged(): void
    {
        $pattern = new SearchPattern(new SearchTerm('saphir'));
        $pattern->setBooleanClause(true);
        $pattern->setTokenTerms([['saphir', 'saphir ring']]);

        $criteria = $this->build($pattern);

        static::assertEquals([
            new AndFilter([
                new EqualsFilter('product.searchKeywords.languageId', self::LANGUAGE_ID),
                new EqualsAnyFilter('product.searchKeywords.keyword', ['saphir', 'saphir ring']),
            ]),
        ], $criteria->getFilters());
        static::assertFalse(self::hasScoredMatchesMarker($criteria));
    }

    private function build(SearchPattern $pattern): Criteria
    {
        $pattern->addTerm(new SearchTerm('saphir ring', 0.5));

        $termInterpreter = static::createStub(ProductSearchTermInterpreterInterface::class);
        $termInterpreter->method('interpret')->willReturn($pattern);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getLanguageId')->willReturn(self::LANGUAGE_ID);

        $criteria = new Criteria();
        $searchBuilder = new ProductSearchBuilder($termInterpreter, static::createStub(LoggerInterface::class), 20);
        $searchBuilder->build(new Request(['search' => 'saphir']), $criteria, $salesChannelContext);

        return $criteria;
    }

    private static function hasScoredMatchesMarker(Criteria $criteria): bool
    {
        foreach ([...$criteria->getFilters(), ...$criteria->getQueries()] as $part) {
            if ($part->hasExtension(ScoredMatchesMarker::EXTENSION_NAME)) {
                return true;
            }
        }

        return false;
    }
}
