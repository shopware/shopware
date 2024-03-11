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
            new Criteria(),
            new Request(),
            1,
            24,
        ];

        yield 'Empty criteria, request with page' => [
            new Criteria(),
            new Request(['p' => 2]),
            2,
            24,
        ];

        yield 'Empty criteria, request with limit' => [
            new Criteria(),
            new Request(['limit' => 50]),
            1,
            50,
        ];

        yield 'Empty criteria, request with page and limit' => [
            new Criteria(),
            new Request(['p' => 2, 'limit' => 50]),
            2,
            50,
        ];

        yield 'Criteria with limit, empty request' => [
            (new Criteria())->setLimit(50),
            new Request(),
            1,
            50,
        ];

        yield 'Criteria with limit, request with page' => [
            (new Criteria())->setLimit(50),
            new Request(['p' => 2]),
            2,
            50,
        ];

        yield 'Criteria with limit, request with limit' => [
            (new Criteria())->setLimit(50),
            new Request(['limit' => 100]),
            1,
            100,
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

        static::assertEquals(($page - 1) * $limit, $criteria->getOffset());
        static::assertEquals($limit, $criteria->getLimit());
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

        static::assertEquals(2, $result->getPage());
        static::assertEquals(10, $result->getLimit());
    }
}
