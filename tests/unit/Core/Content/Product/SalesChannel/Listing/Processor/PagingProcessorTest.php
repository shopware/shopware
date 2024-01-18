<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(PagingListingProcessor::class)]
class PagingProcessorTest extends TestCase
{
    #[DataProvider('prepareProvider')]
    public function testPrepare(Request $request, Criteria $expected, string $method = Request::METHOD_GET): void
    {
        $request->setMethod($method);
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);

        $config = new StaticSystemConfigService([
            'core.listing.productsPerPage' => 24,
        ]);

        $processor = new PagingListingProcessor($config);
        $processor->prepare($request, $criteria, $context);

        static::assertSame($expected->getOffset(), $criteria->getOffset());
        static::assertSame($expected->getLimit(), $criteria->getLimit());
    }

    public static function prepareProvider(): \Generator
    {
        yield 'Provided GET limit will be accepted' => [
            new Request(['limit' => 10]),
            (new Criteria())->setOffset(0)->setLimit(10),
        ];

        yield 'Provided POST limit will be accepted' => [
            new Request([], ['limit' => 10]),
            (new Criteria())->setOffset(0)->setLimit(10),
            Request::METHOD_POST,
        ];

        yield 'Provided page will be accepted' => [
            new Request(['p' => 2]),
            (new Criteria())->setOffset(24)->setLimit(24),
        ];

        yield 'Provided page and limit will be accepted' => [
            new Request(['p' => 2, 'limit' => 10]),
            (new Criteria())->setOffset(10)->setLimit(10),
        ];

        yield 'Provided page and POST limit will be accepted' => [
            new Request([], ['p' => 2, 'limit' => 10]),
            (new Criteria())->setOffset(10)->setLimit(10),
            Request::METHOD_POST,
        ];

        yield 'Provided page and GET limit will be accepted' => [
            new Request(['p' => 2, 'limit' => 10]),
            (new Criteria())->setOffset(10)->setLimit(10),
        ];

        yield 'Test negative limit' => [
            new Request(['limit' => -1]),
            (new Criteria())->setOffset(0)->setLimit(24),
        ];

        yield 'Test negative page' => [
            new Request(['p' => -1]),
            (new Criteria())->setOffset(0)->setLimit(24),
        ];
    }

    public function testProcess(): void
    {
        $request = new Request(['p' => 2]);
        $criteria = new Criteria();
        $criteria->setLimit(24);

        $result = new ProductListingResult('foo', 100, new ProductCollection(), null, $criteria, Context::createDefaultContext());
        $context = $this->createMock(SalesChannelContext::class);

        $config = new StaticSystemConfigService([
            'core.listing.productsPerPage' => 24,
        ]);

        $processor = new PagingListingProcessor($config);
        $processor->process($request, $result, $context);

        static::assertSame(2, $result->getPage());
        static::assertSame(24, $result->getLimit());
    }
}
