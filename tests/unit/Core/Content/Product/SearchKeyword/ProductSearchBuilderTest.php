<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Defaults;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSearchBuilder::class)]
class ProductSearchBuilderTest extends TestCase
{
    public function testFallbackToCriteriaTermWhenSearchKeywordIndexingIsDisabled(): void
    {
        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20,
            false
        );

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $criteria = new Criteria();
        $request = new Request();
        $request->query->set('search', 'ring saphir');

        $termInterpreter->expects($this->never())->method('interpret');

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        static::assertSame('ring saphir', $criteria->getTerm());
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

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
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

    public function testIntermediateStrictnessCreatesCombinationFilter(): void
    {
        $pattern = new SearchPattern(new SearchTerm('foo bar baz'));
        $pattern->setMinimumShouldMatch(2);
        $pattern->setTokenTerms([
            ['foo'],
            ['bar'],
            ['baz'],
        ]);

        $termInterpreter = $this->createMock(ProductSearchTermInterpreterInterface::class);
        $termInterpreter->expects($this->once())
            ->method('interpret')
            ->willReturn($pattern);

        $logger = $this->createMock(LoggerInterface::class);
        $searchBuilder = new ProductSearchBuilder(
            $termInterpreter,
            $logger,
            20
        );

        $mockSalesChannelContext = $this->createMock(SalesChannelContext::class);
        $mockSalesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $mockSalesChannelContext->method('getLanguageId')->willReturn(Defaults::LANGUAGE_SYSTEM);

        $criteria = new Criteria();
        $request = new Request();
        $request->query->set('search', 'foo bar baz');

        $searchBuilder->build($request, $criteria, $mockSalesChannelContext);

        $filters = $criteria->getFilters();

        static::assertCount(1, $filters);
        static::assertInstanceOf(OrFilter::class, $filters[0]);
        static::assertCount(3, $filters[0]->getQueries());

        foreach ($filters[0]->getQueries() as $combination) {
            static::assertInstanceOf(AndFilter::class, $combination);
            static::assertCount(2, $combination->getQueries());
        }
    }
}
