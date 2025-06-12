<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PagingListingProcessor::class)]
class PagingListingProcessorTest extends TestCase
{
    public static function provideTestPrepare(): \Generator
    {
        yield 'Empty criteria, empty request' => [
            'criteria' => new Criteria(),
            'request' => new Request(),
            'page' => 1,
            'limit' => 24,
        ];

        yield 'Empty criteria, request with page' => [
            'criteria' => new Criteria(),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 24,
        ];

        yield 'Criteria with limit, empty request' => [
            'criteria' => (new Criteria())->setLimit(50),
            'request' => new Request(),
            'page' => 1,
            'limit' => 50,
        ];

        yield 'Criteria with limit, request with page' => [
            'criteria' => (new Criteria())->setLimit(50),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, empty request' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(50),
            'request' => new Request(),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, request with page (should use request query parameter over criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, request with page (should use request body parameter over criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(request: ['p' => 2]),
            'page' => 2,
            'limit' => 50,
        ];

        yield 'Criteria with limit & page, post request with page (should use request body parameter over query parameter and criteria)' => [
            'criteria' => (new Criteria())->setLimit(50)->setOffset(200),
            'request' => new Request(['p' => 2], ['p' => 3]),
            'page' => 3,
            'limit' => 50,
        ];
    }

    #[DataProvider('provideTestPrepare')]
    public function testPrepare(Criteria $criteria, Request $request, int $page, int $limit): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new PagingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.productsPerPage' => 24,
            ])
        );

        $processor->prepare($request, $criteria, $context);

        static::assertSame(($page - 1) * $limit, $criteria->getOffset());
        static::assertSame($limit, $criteria->getLimit());
    }

    public function testProcess(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $request = new Request(['p' => 2]);
        $context = $this->createMock(SalesChannelContext::class);

        $processor = new PagingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.productsPerPage' => 24,
            ])
        );

        $result = new ProductListingResult('product', 10, new ProductCollection(), new AggregationResultCollection(), $criteria, Context::createDefaultContext());

        $processor->process($request, $result, $context);

        static::assertSame(2, $result->getPage());
        static::assertSame(10, $result->getLimit());
    }
}
